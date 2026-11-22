//! `scarlet-part`: the upload unit of Scarlet. One implementation of the
//! sharing groups, the part format, the wire batch and the decoder, used by the
//! desktop companion and by the server. See docs/DESIGN.md.

pub mod baselines;
pub mod build;
pub mod camp;
pub mod combat;
pub mod compare;
pub mod counts;
pub mod death;
pub mod decode;
pub mod fishing;
pub mod group;
pub mod groups;
pub mod imports;
pub mod load;
pub mod payload;
pub mod peer;
pub mod professions;
pub mod pseudo;
pub mod reconcile;
pub mod reference;
pub mod rows;
pub mod sets;
pub mod social;
pub mod tables;
pub mod talents;
pub mod wire;
