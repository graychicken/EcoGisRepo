-- Add statistic permission
INSERT INTO auth.acnames (ac_verb, ac_name, app_id, ac_mod_user) VALUES ('SHOW', 'STATISTIC', (SELECT app_id FROM auth.applications WHERE app_code='ECOGIS2'), 0);
INSERT INTO auth.groups_acl (gr_id, ac_id, ga_kind)
SELECT gr_id, (SELECT ac_id FROM auth.acnames WHERE ac_name = 'STATISTIC' AND ac_verb = 'SHOW' AND app_id=(SELECT app_id FROM auth.applications WHERE app_code='ECOGIS2')), 'A'
FROM (
	SELECT gr_id
	FROM auth.groups
	WHERE groups.app_id = (SELECT app_id FROM auth.applications WHERE app_code='ECOGIS2')
	
) AS foo;


-- Add heating degree days table
CREATE TABLE ecogis.heating_degree_day (
  hdd_id SERIAL,
  mu_id integer not null,
  hdd_year INTEGER NOT NULL,
  hdd_factor DOUBLE PRECISION NOT NULL,
  CONSTRAINT heating_degree_day_pkey PRIMARY KEY(hdd_id)
);
CREATE UNIQUE INDEX heating_degree_day_idx ON ecogis.heating_degree_day USING btree (mu_id, hdd_year);
ALTER TABLE ecogis.heating_degree_day ADD CONSTRAINT heating_degree_day_fk FOREIGN KEY (mu_id) REFERENCES ecogis.municipality(mu_id) ON DELETE NO ACTION ON UPDATE NO ACTION NOT DEFERRABLE;

-- Add export privileges
INSERT INTO auth.acnames (ac_verb, ac_name, app_id, ac_mod_user) VALUES ('EXPORT', 'BUILDING', (SELECT app_id FROM auth.applications WHERE app_code='ECOGIS2'), 0);
INSERT INTO auth.groups_acl (gr_id, ac_id, ga_kind)
SELECT gr_id, (SELECT ac_id FROM auth.acnames WHERE ac_verb = 'EXPORT' AND ac_name = 'BUILDING' AND app_id=(SELECT app_id FROM auth.applications WHERE app_code='ECOGIS2')), 'A'
FROM (
	SELECT DISTINCT groups.gr_id
	FROM auth.groups
	INNER JOIN auth.groups_acl ON groups.gr_id=groups_acl.gr_id and ga_kind='A'
	INNER JOIN auth.acnames ON acnames.ac_id=groups_acl.ac_id
	WHERE groups.app_id = (SELECT app_id FROM auth.applications WHERE app_code='ECOGIS2')
	    AND ac_verb = 'SHOW' AND ac_name = 'BUILDING'
) AS foo;

INSERT INTO ecogis.version(dbv_database_version, dbv_application_version) VALUES ('1.32', '2.10.1');

