DROP TABLE audit_item CASCADE CONSTRAINTS;
DROP TABLE audit_participant CASCADE CONSTRAINTS;
DROP TABLE notes CASCADE CONSTRAINTS;
DROP TABLE audits CASCADE CONSTRAINTS;
DROP TABLE waste CASCADE CONSTRAINTS;
DROP TABLE auditors CASCADE CONSTRAINTS;
DROP TABLE locations CASCADE CONSTRAINTS;

CREATE TABLE locations
( location_id CHAR(6),
  name VARCHAR2(200),
  floor_num NUMBER,
  loc_type VARCHAR2(50),
  PRIMARY KEY (location_id)
);

CREATE TABLE auditors
( auditor_id CHAR(6),
  fname VARCHAR2(50),
  lname VARCHAR2(50),
  affiliation VARCHAR2(50),
  PRIMARY KEY (auditor_id)
);

CREATE TABLE waste
( waste_id CHAR(6),
  category VARCHAR2(100),
  parent_waste_id CHAR(6),
  PRIMARY KEY (waste_id),
  FOREIGN KEY (parent_waste_id) REFERENCES waste(waste_id)
);

CREATE TABLE audits
( audit_id CHAR(6),
  location_id CHAR(6),
  audited_at DATE,
  num_bags NUMBER,
  contamination_flag NUMBER(1),
  PRIMARY KEY (audit_id),
  FOREIGN KEY (location_id) REFERENCES locations(location_id)
);

CREATE TABLE notes
( note_id CHAR(6),
  audit_id CHAR(6),
  note_text VARCHAR2(1000),
  created_at DATE,
  PRIMARY KEY (note_id),
  FOREIGN KEY (audit_id) REFERENCES audits(audit_id),
  FOREIGN KEY (created_at) REFERENCES audits(audited_at)
);

CREATE TABLE audit_participant
( audit_id CHAR(6),
  auditor_id CHAR(6),
  role_label VARCHAR2(40),
  PRIMARY KEY (audit_id, auditor_id),
  FOREIGN KEY (audit_id) REFERENCES audits(audit_id),
  FOREIGN KEY (auditor_id) REFERENCES auditors(auditor_id)
);

CREATE TABLE audit_item
( audit_item_id CHAR(8),
  audit_id CHAR(6),
  waste_id CHAR(6),
  abs_mass NUMBER,
  rel_mass NUMBER,
  rel_volume NUMBER,
  PRIMARY KEY (audit_item_id),
  FOREIGN KEY (audit_id) REFERENCES audits(audit_id),
  FOREIGN KEY (waste_id) REFERENCES waste(waste_id)
);
