DROP SCHEMA IF EXISTS "digitalResource" CASCADE;

CREATE SCHEMA "digitalResource";


-- Table: "digitalResource"."cluster"

-- DROP TABLE "digitalResource"."cluster";

CREATE TABLE "digitalResource"."cluster"
(

  "clusterId" text NOT NULL,
  "clusterName" text,
  "clusterDescription" text,
  PRIMARY KEY ("clusterId")
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."digitalResource"

-- DROP TABLE "digitalResource"."digitalResource";

CREATE TABLE "digitalResource"."digitalResource"
(
  "archiveId" text NOT NULL,
  "resId" text NOT NULL,
  "clusterId" text NOT NULL,
  "size" bigint NOT NULL,
  "puid" text,
  "mimetype" text,
  "hash" text,
  "hashAlgorithm" text,
  "fileExtension" text,
  "fileName" text,
  "mediaInfo" text,
  "created" timestamp NOT NULL,
  "updated" timestamp,
  "relatedResId" text,
  "relationshipType" text,
  PRIMARY KEY ("resId"),
  FOREIGN KEY ("archiveId")
      REFERENCES "recordsManagement"."archive" ("archiveId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  FOREIGN KEY ("clusterId")
      REFERENCES "digitalResource"."cluster" ("clusterId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  FOREIGN KEY ("relatedResId")
      REFERENCES "digitalResource"."digitalResource" ("resId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."repository"

-- DROP TABLE "digitalResource"."repository";

CREATE TABLE "digitalResource"."repository"
(
  "repositoryId" text NOT NULL,
  "repositoryName" text NOT NULL,
  "repositoryReference" text NOT NULL,
  "repositoryType" text NOT NULL,
  "repositoryUri" text NOT NULL,
  "parameters" text,
  "maxSize" integer,
  "enabled" boolean,
  PRIMARY KEY ("repositoryId"),
  UNIQUE ("repositoryReference"),
  UNIQUE ("repositoryUri")
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."address"

-- DROP TABLE "digitalResource"."address";

CREATE TABLE "digitalResource"."address"
(
  "resId" text NOT NULL,
  "repositoryId" text NOT NULL,
  "path" text NOT NULL,
  "lastIntegrityCheck" timestamp,
  "integrityCheckResult" boolean,
  "packed" boolean default false,
  "created" timestamp NOT NULL,
  PRIMARY KEY ("resId", "repositoryId"),
  FOREIGN KEY ("resId")
      REFERENCES "digitalResource"."digitalResource" ("resId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  FOREIGN KEY ("repositoryId")
      REFERENCES "digitalResource"."repository" ("repositoryId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."clusterRepositories"

-- DROP TABLE "digitalResource"."clusterRepositories";

CREATE TABLE "digitalResource"."clusterRepository"
(
  "clusterId" text NOT NULL,
  "repositoryId" text NOT NULL,
  "writePriority" integer,
  "readPriority" integer,
  "deletePriority" integer,
  PRIMARY KEY ("clusterId", "repositoryId"),
  FOREIGN KEY ("clusterId")
      REFERENCES "digitalResource"."cluster" ("clusterId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  FOREIGN KEY ("repositoryId")
      REFERENCES "digitalResource"."repository" ("repositoryId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."package"

-- DROP TABLE "digitalResource"."package";

CREATE TABLE "digitalResource"."package"
(
  "packageId" text NOT NULL,
  "method" text NOT NULL,
  PRIMARY KEY ("packageId"),
  FOREIGN KEY ("packageId")
      REFERENCES "digitalResource"."digitalResource" ("resId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);


-- Table: "digitalResource"."packedResource"

-- DROP TABLE "digitalResource"."packedResource";

CREATE TABLE "digitalResource"."packedResource"
(
  "packageId" text NOT NULL,
  "resId" text NOT NULL,
  "name" text NOT NULL,
  FOREIGN KEY ("packageId")
      REFERENCES "digitalResource"."package" ("packageId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  FOREIGN KEY ("resId")
      REFERENCES "digitalResource"."digitalResource" ("resId") MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."contentType"

-- DROP TABLE "digitalResource"."contentType";

CREATE TABLE "digitalResource"."contentType"
(
  "name" text NOT NULL,
  "mediatype" text NOT NULL,
  "description" text,
  "puids" text,
  "validationMode" text,
  "conversionMode" text,
  "textExtractionMode" text,
  "metadataExtractionMode" text,
  PRIMARY KEY ("name")
)
WITH (
  OIDS=FALSE
);

-- Table: "digitalResource"."conversionRule"

-- DROP TABLE "digitalResource"."conversionRule";

CREATE TABLE "digitalResource"."conversionRule"
(
  "conversionRuleId" text NOT NULL,
  "puid" text NOT NULL,
  "conversionService" text NOT NULL,
  "targetPuid" text NOT NULL,
  PRIMARY KEY ("conversionRuleId"),
  UNIQUE (puid)
)
WITH (
  OIDS=FALSE
);

CREATE INDEX "digitalResource_digitalResource_archiveId_idx" ON "digitalResource"."digitalResource" USING btree ("archiveId");

CREATE INDEX "digitalResource_digitalResource_relatedResId__relationshipType_idx" ON "digitalResource"."digitalResource" USING btree ("relatedResId", "relationshipType");

CREATE TABLE "digitalResource"."format" 
(
    "puid" text NOT NULL,
    "name" text,
    "version" text,
    "mimetypes" text,
    "extensions" text,
    "status" integer,

    PRIMARY KEY ("puid")
);
