//! `scarlet-part`: the upload unit of Scarlet. One implementation of the
//! sharing groups, the part format, the wire batch and the decoder, used by the
//! desktop companion and by the server. See docs/DESIGN.md.

pub mod auras;
pub mod baselines;
pub mod bosses;
pub mod build;
pub mod camp;
pub mod class;
pub mod combat;
pub mod compare;
pub mod counts;
pub mod death;
pub mod decode;
pub mod fates;
pub mod fishing;
pub mod flows;
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
pub mod spans;
pub mod states;
pub mod tables;
pub mod talents;
pub mod tallies;
pub mod wire;
