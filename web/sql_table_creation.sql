-- Table: public.request_admin

-- DROP TABLE IF EXISTS public.request_admin;

CREATE TABLE IF NOT EXISTS public.request_admin
(
  id_request_admin integer NOT NULL DEFAULT nextval('request_admin_id_request_admin_seq'::regclass),
  no_request character varying(255) COLLATE pg_catalog."default" NOT NULL DEFAULT 'RA-000'::character varying,
  tgl_request timestamp without time zone DEFAULT now(),
  uid_request integer,
  keterangan text COLLATE pg_catalog."default",
  uid_changed integer,
  created timestamp without time zone DEFAULT now(),
  changed timestamp without time zone,
  CONSTRAINT request_admin_pkey PRIMARY KEY (id_request_admin)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_admin
  OWNER to postgres;

COMMENT ON TABLE public.request_admin
    IS 'Tabel berisi informasi request produk dari admin ke bagian stok';

  -- Table: public.request_admin_detail

-- DROP TABLE IF EXISTS public.request_admin_detail;

CREATE TABLE IF NOT EXISTS public.request_admin_detail
(
  id_request_admin_detail integer NOT NULL DEFAULT nextval('request_admin_detail_id_request_admin_detail_seq'::regclass),
  id_product integer NOT NULL,
  qty_request integer NOT NULL DEFAULT 1,
  keterangan text COLLATE pg_catalog."default",
  created timestamp without time zone DEFAULT now(),
  changed timestamp without time zone,
  uid_created integer,
  uid_changed integer,
  CONSTRAINT request_admin_detail_pkey PRIMARY KEY (id_request_admin_detail)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_admin_detail
  OWNER to postgres;

COMMENT ON TABLE public.request_admin_detail
    IS 'Detail produk yang di request oleh admin pada bagian stok';
-- Column: public.request_admin.status_request

-- ALTER TABLE IF EXISTS public.request_admin DROP COLUMN IF EXISTS status_request;

ALTER TABLE IF EXISTS public.request_admin
  ADD COLUMN status_request smallint NOT NULL DEFAULT 0;

-- Column: public.request_admin_detail.status_detail

-- ALTER TABLE IF EXISTS public.request_admin_detail DROP COLUMN IF EXISTS status_detail;

ALTER TABLE IF EXISTS public.request_admin_detail
  ADD COLUMN status_detail smallint DEFAULT 0;

-- Column: public.request_admin_detail.id_request_admin

-- ALTER TABLE IF EXISTS public.request_admin_detail DROP COLUMN IF EXISTS id_request_admin;

ALTER TABLE IF EXISTS public.request_admin_detail
  ADD COLUMN id_request_admin integer;

COMMENT ON COLUMN public.request_admin_detail.id_request_admin
    IS 'Related request admin';
-- Column: public.request_admin.nama_pemesan

-- ALTER TABLE IF EXISTS public.request_admin DROP COLUMN IF EXISTS nama_pemesan;

ALTER TABLE IF EXISTS public.request_admin
  ADD COLUMN nama_pemesan character varying(255) COLLATE pg_catalog."default" DEFAULT NULL::character varying;
-- Column: public.request_admin.file_attachment

-- ALTER TABLE IF EXISTS public.request_admin DROP COLUMN IF EXISTS file_attachment;

ALTER TABLE IF EXISTS public.request_admin
  ADD COLUMN file_attachment character varying(255) COLLATE pg_catalog."default" DEFAULT NULL::character varying;

-- Column: public.request_admin.file_id

-- ALTER TABLE IF EXISTS public.request_admin DROP COLUMN IF EXISTS file_id;

ALTER TABLE IF EXISTS public.request_admin
  ADD COLUMN file_id integer;

-- Table: public.request_produksi

-- DROP TABLE IF EXISTS public.request_produksi;

CREATE TABLE IF NOT EXISTS public.request_produksi
(
  id_request_produksi integer NOT NULL DEFAULT nextval('request_produksi_id_request_produksi_seq'::regclass),
  CONSTRAINT request_produksi_pkey PRIMARY KEY (id_request_produksi)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_produksi
  OWNER to postgres;
-- Column: public.request_produksi.tgl_request_produksi

-- ALTER TABLE IF EXISTS public.request_produksi DROP COLUMN IF EXISTS tgl_request_produksi;

ALTER TABLE IF EXISTS public.request_produksi
  ADD COLUMN tgl_request_produksi timestamp without time zone NOT NULL DEFAULT now();

-- Column: public.request_produksi.uid_request

-- ALTER TABLE IF EXISTS public.request_produksi DROP COLUMN IF EXISTS uid_request;

ALTER TABLE IF EXISTS public.request_produksi
  ADD COLUMN uid_request integer;
-- Column: public.request_produksi.changed

-- ALTER TABLE IF EXISTS public.request_produksi DROP COLUMN IF EXISTS changed;

ALTER TABLE IF EXISTS public.request_produksi
  ADD COLUMN changed timestamp without time zone;

-- Column: public.request_produksi.uid_changed

-- ALTER TABLE IF EXISTS public.request_produksi DROP COLUMN IF EXISTS uid_changed;

ALTER TABLE IF EXISTS public.request_produksi
  ADD COLUMN uid_changed integer;
-- Table: public.request_admin_produksi

-- DROP TABLE IF EXISTS public.request_admin_produksi;

CREATE TABLE IF NOT EXISTS public.request_admin_produksi
(
  id_request_admin integer NOT NULL,
  id_request_produksi integer NOT NULL,
  CONSTRAINT request_admin_produksi_pkey PRIMARY KEY (id_request_admin, id_request_produksi)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_admin_produksi
  OWNER to postgres;
-- Table: public.request_produksi_detail

-- DROP TABLE IF EXISTS public.request_produksi_detail;

CREATE TABLE IF NOT EXISTS public.request_produksi_detail
(
  id_request_produksi_detail integer NOT NULL DEFAULT nextval('request_produksi_detail_id_request_produksi_detail_seq'::regclass),
  id_request_produksi integer NOT NULL,
  produk_produksi character varying(255) COLLATE pg_catalog."default" NOT NULL,
  gramasi character varying(255) COLLATE pg_catalog."default" NOT NULL,
  total_qty integer NOT NULL DEFAULT 1,
  status_produksi_produk smallint NOT NULL DEFAULT 0,
  uid_created integer,
  created timestamp without time zone DEFAULT now(),
  CONSTRAINT request_produksi_detail_pkey PRIMARY KEY (id_request_produksi_detail)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_produksi_detail
  OWNER to postgres;
-- Column: public.request_produksi.status_produksi

-- ALTER TABLE IF EXISTS public.request_produksi DROP COLUMN IF EXISTS status_produksi;

ALTER TABLE IF EXISTS public.request_produksi
  ADD COLUMN status_produksi integer DEFAULT 0;

-- Table: public.request_kemasan

-- DROP TABLE IF EXISTS public.request_kemasan;

CREATE TABLE IF NOT EXISTS public.request_kemasan
(
  id_request_kemasan integer NOT NULL DEFAULT nextval('request_kemasan_id_request_kemasan_seq'::regclass),
  tgl_request_kemasan timestamp without time zone DEFAULT now(),
  keterangan text COLLATE pg_catalog."default",
  uid_request integer,
  created timestamp without time zone DEFAULT now(),
  changed timestamp without time zone,
  uid_changed integer,
  status_kemasan smallint DEFAULT 0,
  CONSTRAINT request_kemasan_pkey PRIMARY KEY (id_request_kemasan)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_kemasan
  OWNER to postgres;
-- Table: public.request_kemasan_detail

-- DROP TABLE IF EXISTS public.request_kemasan_detail;

CREATE TABLE IF NOT EXISTS public.request_kemasan_detail
(
  id_request_kemasan_detail integer NOT NULL DEFAULT nextval('request_kemasan_detail_id_request_kemasan_detail_seq'::regclass),
  id_request_kemasan integer NOT NULL,
  id_product integer NOT NULL,
  total_qty integer NOT NULL,
  status_kemasan_produk smallint DEFAULT 0,
  uid_created integer,
  created timestamp without time zone DEFAULT now(),
  CONSTRAINT request_kemasan_detail_pkey PRIMARY KEY (id_request_kemasan_detail)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_kemasan_detail
  OWNER to postgres;
-- Table: public.request_admin_kemasan

-- DROP TABLE IF EXISTS public.request_admin_kemasan;

CREATE TABLE IF NOT EXISTS public.request_admin_kemasan
(
  id_request_admin integer NOT NULL,
  id_request_kemasan integer NOT NULL,
  CONSTRAINT request_admin_kemasan_pkey PRIMARY KEY (id_request_kemasan, id_request_admin)
  )

  TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.request_admin_kemasan
  OWNER to postgres;

-- Column: public.request_kemasan_detail.total_qty_actual

-- ALTER TABLE IF EXISTS public.request_kemasan_detail DROP COLUMN IF EXISTS total_qty_actual;

ALTER TABLE IF EXISTS public.request_kemasan_detail
  ADD COLUMN total_qty_actual integer NOT NULL DEFAULT 0;

-- Column: public.request_produksi_detail.total_qty_actual

-- ALTER TABLE IF EXISTS public.request_produksi_detail DROP COLUMN IF EXISTS total_qty_actual;

ALTER TABLE IF EXISTS public.request_produksi_detail
  ADD COLUMN total_qty_actual integer DEFAULT 0;
