CREATE TABLE "digitalResource"."format" 
(
    "puid" text NOT NULL,
    "name" text,
    "version" text,
    "mimetypes" text,
    "extensions" text,
    "sustainability" boolean DEFAULT true,
    "enabled" boolean DEFAULT true,

    PRIMARY KEY ("puid")
);