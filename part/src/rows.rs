//! Newline-delimited JSON rows, written with a fixed key order.

use std::fmt::Write;

pub struct Rows<'a> {
    out: &'a mut String,
    pub count: usize,
}

impl<'a> Rows<'a> {
    pub fn new(out: &'a mut String) -> Self {
        Rows { out, count: 0 }
    }

    /// Start a row of `table`.
    pub fn begin(&mut self, table: &str) {
        self.out.push_str("{\"t\":\"");
        self.out.push_str(table);
        self.out.push('"');
        self.count += 1;
    }

    pub fn int(&mut self, key: &str, v: i64) -> &mut Self {
        let _ = write!(self.out, ",\"{key}\":{v}");
        self
    }

    pub fn opt(&mut self, key: &str, v: Option<i64>) -> &mut Self {
        match v {
            Some(v) => self.int(key, v),
            None => self.null(key),
        }
    }

    pub fn null(&mut self, key: &str) -> &mut Self {
        let _ = write!(self.out, ",\"{key}\":null");
        self
    }

    pub fn bool(&mut self, key: &str, v: bool) -> &mut Self {
        let _ = write!(self.out, ",\"{key}\":{v}");
        self
    }

    pub fn opt_bool(&mut self, key: &str, v: Option<bool>) -> &mut Self {
        match v {
            Some(v) => self.bool(key, v),
            None => self.null(key),
        }
    }

    pub fn str(&mut self, key: &str, v: &str) -> &mut Self {
        let _ = write!(self.out, ",\"{key}\":");
        self.quoted(v);
        self
    }

    /// A string, or null when empty.
    pub fn text(&mut self, key: &str, v: &str) -> &mut Self {
        if v.is_empty() {
            self.null(key)
        } else {
            self.str(key, v)
        }
    }

    pub fn strs(&mut self, key: &str, v: &[&str]) -> &mut Self {
        let _ = write!(self.out, ",\"{key}\":[");
        for (i, s) in v.iter().enumerate() {
            if i > 0 {
                self.out.push(',');
            }
            self.quoted(s);
        }
        self.out.push(']');
        self
    }

    pub fn end(&mut self) {
        self.out.push_str("}\n");
    }

    fn quoted(&mut self, v: &str) {
        self.out.push('"');
        for c in v.chars() {
            match c {
                '"' => self.out.push_str("\\\""),
                '\\' => self.out.push_str("\\\\"),
                '\n' => self.out.push_str("\\n"),
                '\r' => self.out.push_str("\\r"),
                '\t' => self.out.push_str("\\t"),
                c if (c as u32) < 0x20 => {
                    let _ = write!(self.out, "\\u{:04x}", c as u32);
                }
                c => self.out.push(c),
            }
        }
        self.out.push('"');
    }
}
