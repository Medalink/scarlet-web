//! The upload API as the app uses it: signed requests, sync, manifest, parts, status (docs/API.md).

use ed25519_dalek::{Signer, SigningKey};
use scarlet_part::pseudo::hex;
use sha2::{Digest, Sha256};
use std::net::{SocketAddr, ToSocketAddrs};
use std::path::Path;
use std::time::{Duration, Instant, SystemTime, UNIX_EPOCH};
use ureq::tls::{Certificate, RootCerts, TlsConfig};
use ureq::unversioned::resolver::{ResolvedSocketAddrs, Resolver};
use ureq::unversioned::transport::{DefaultConnector, NextTimeout};

pub struct Reply {
    pub status: u16,
    pub body: Vec<u8>,
    pub ms: u32,
    pub sent: usize,
}

pub struct Identity {
    key: SigningKey,
    pub id: String,
}

pub struct Api {
    agent: ureq::Agent,
    base: String,
}

const B64: &[u8; 64] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

pub fn base64(bytes: &[u8]) -> String {
    let mut out = String::with_capacity(bytes.len().div_ceil(3) * 4);
    for chunk in bytes.chunks(3) {
        let n = (u32::from(chunk[0]) << 16)
            | (u32::from(*chunk.get(1).unwrap_or(&0)) << 8)
            | u32::from(*chunk.get(2).unwrap_or(&0));
        for i in 0..4 {
            if i <= chunk.len() {
                out.push(B64[((n >> (18 - 6 * i)) & 63) as usize] as char);
            } else {
                out.push('=');
            }
        }
    }
    out
}

pub fn now_seconds() -> i64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map_or(0, |d| d.as_secs() as i64)
}

impl Identity {
    pub fn from_seed(seed: [u8; 32]) -> Identity {
        Identity {
            key: SigningKey::from_bytes(&seed),
            id: String::new(),
        }
    }

    pub fn public_key(&self) -> String {
        base64(self.key.verifying_key().as_bytes())
    }
}

#[derive(Debug)]
struct Fixed(SocketAddr);

impl Resolver for Fixed {
    fn resolve(
        &self,
        _uri: &ureq::http::Uri,
        _config: &ureq::config::Config,
        _timeout: NextTimeout,
    ) -> Result<ResolvedSocketAddrs, ureq::Error> {
        let mut addresses = self.empty();
        addresses.push(self.0);
        Ok(addresses)
    }
}

/// The only address a request can reach: the one `name` resolves to, when `name` is scarlet-load-* and that is a private or
/// tailnet address. Every lookup the HTTP client makes answers with it, whatever the Host header says.
pub fn guard_connect(name: &str) -> Result<SocketAddr, String> {
    let host = name.split(':').next().unwrap_or("");
    let allowed = host.starts_with("scarlet-load-")
        && host
            .bytes()
            .all(|b| b.is_ascii_lowercase() || b.is_ascii_digit() || b == b'-');
    if !allowed {
        return Err(format!(
            "{host} is not a scarlet-load-* host; the generator never connects to anything else"
        ));
    }
    let port: u16 = name
        .split_once(':')
        .map_or(Ok(443), |(_, p)| p.parse())
        .map_err(|_| "bad port")?;
    let addresses: Vec<SocketAddr> = (host, port)
        .to_socket_addrs()
        .map_err(|e| format!("{host}: {e}"))?
        .collect();
    let private = |ip: std::net::IpAddr| match ip {
        std::net::IpAddr::V4(v4) => {
            v4.is_private() || (v4.octets()[0] == 100 && (64..128).contains(&v4.octets()[1]))
        }
        std::net::IpAddr::V6(_) => false,
    };
    match addresses.first() {
        Some(a) if addresses.iter().all(|x| private(x.ip())) => Ok(*a),
        _ => Err(format!(
            "{host} resolves to {addresses:?}, which is not a private or tailnet address"
        )),
    }
}

impl Api {
    pub fn new(connect: &str, host: &str, ca: Option<&Path>) -> Result<Api, String> {
        let address = guard_connect(connect)?;
        let mut tls = TlsConfig::builder();
        if let Some(path) = ca {
            let pem = std::fs::read(path).map_err(|e| format!("{}: {e}", path.display()))?;
            let cert = Certificate::from_pem(&pem).map_err(|e| e.to_string())?;
            tls = tls.root_certs(RootCerts::new_with_certs(&[cert]));
        }
        let config = ureq::Agent::config_builder()
            .http_status_as_error(false)
            .max_redirects(0)
            .timeout_global(Some(Duration::from_secs(120)))
            .max_idle_connections_per_host(256)
            .max_idle_connections(256)
            .tls_config(tls.build())
            .build();
        Ok(Api {
            agent: ureq::Agent::with_parts(config, DefaultConnector::default(), Fixed(address)),
            base: format!("https://{host}"),
        })
    }

    pub fn call(
        &self,
        method: &str,
        path: &str,
        who: Option<&Identity>,
        content_type: &str,
        body: &[u8],
    ) -> Reply {
        let started = Instant::now();
        let url = format!("{}{path}", self.base);
        let result = match (method, who) {
            ("GET", None) => self.agent.get(&url).call(),
            ("GET", Some(id)) => signed(self.agent.get(&url), id, method, path, body).call(),
            (_, None) => self
                .agent
                .post(&url)
                .header("content-type", content_type)
                .send(body),
            ("DELETE", Some(id)) => signed(self.agent.delete(&url), id, method, path, body).call(),
            (_, Some(id)) => signed(self.agent.post(&url), id, method, path, body)
                .header("content-type", content_type)
                .send(body),
        };
        let ms = started.elapsed().as_millis().min(u128::from(u32::MAX)) as u32;
        match result {
            Ok(mut response) => {
                let status = response.status().as_u16();
                let body_bytes = response.body_mut().read_to_vec().unwrap_or_default();
                Reply {
                    status,
                    body: body_bytes,
                    ms,
                    sent: body.len(),
                }
            }
            Err(e) => Reply {
                status: 0,
                body: e.to_string().into_bytes(),
                ms,
                sent: body.len(),
            },
        }
    }
}

fn signed<B>(
    request: ureq::RequestBuilder<B>,
    id: &Identity,
    method: &str,
    path: &str,
    body: &[u8],
) -> ureq::RequestBuilder<B> {
    let time = now_seconds();
    let digest = hex(&Sha256::digest(body));
    let message = format!("{method}\n{path}\n{time}\n{digest}");
    let signature = id.key.sign(message.as_bytes());
    request
        .header("scarlet-install", &id.id)
        .header("scarlet-time", &time.to_string())
        .header("scarlet-signature", &base64(&signature.to_bytes()))
}
