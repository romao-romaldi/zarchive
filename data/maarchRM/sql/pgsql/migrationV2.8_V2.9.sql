INSERT INTO "lifeCycle"."eventFormat" ("type","format","message","notification") VALUES
('recordsManagement/completenessCheck', 'lastCheckedResId lastCheckedResCreated repositoryReference resourcesToCheck checkedResources failed timeout timeoutError', 'Contrôle d''exhaustivité des ressources', false);

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
