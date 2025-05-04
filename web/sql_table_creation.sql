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
