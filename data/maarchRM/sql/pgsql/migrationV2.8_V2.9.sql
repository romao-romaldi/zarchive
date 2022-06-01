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