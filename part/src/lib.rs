//! `scarlet-part`: the upload unit of Scarlet. One implementation of the
//! sharing groups, the part format, the wire batch and the decoder, used by the
//! desktop companion and by the server. See docs/DESIGN.md.

pub mod build;
pub mod camp;
pub mod compare;
pub mod decode;
pub mod groups;
pub mod imports;
pub mod load;
pub mod payload;
pub mod pseudo;
pub mod rows;
pub mod tables;
pub mod talents;
pub mod wire;
