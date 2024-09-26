insert into toko_griyanaura.lv_transaksijenis (id_transaksijenis, nama, id_transaksigrup) values 
	('persediaan_penyesuaian', 'Penyesuaian Persediaan', 'persediaan');

CREATE SEQUENCE toko_griyanaura.tr_penyesuaiangudang_transaksi_no_seq;

CREATE OR REPLACE FUNCTION toko_griyanaura.get_penyesuaiangudang_transaksi_no()
  RETURNS text AS
$BODY$
declare 
	p_val int; 
	v_ret text;
begin
	p_val := nextval('toko_griyanaura.tr_penyesuaiangudang_transaksi_no_seq'::regclass);
	v_ret := 'WA' || lpad(p_val::text, 6, '0');

	return v_ret;
end;
$BODY$
  LANGUAGE plpgsql;

create table toko_griyanaura.tr_penyesuaiangudang (
	id_penyesuaiangudang serial primary key,
	id_transaksi int references toko_griyanaura.tr_transaksi(id_transaksi) on update cascade on delete restrict,
	transaksi_no varchar(50) default toko_griyanaura.get_penyesuaiangudang_transaksi_no(),
	keterangan varchar(200),
	catatan varchar(400),
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying,
	tanggal timestamp(0) default current_timestamp(0)
);

create table toko_griyanaura.tr_penyesuaiangudangdetail (
	id_penyesuaiangudangdetail serial primary key,
	id_penyesuaiangudang int references toko_griyanaura.tr_penyesuaiangudang(id_penyesuaiangudang) on update cascade on delete restrict,
	id_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict,
	kode_produkvarian varchar(50) references toko_griyanaura.ms_produkvarian(kode_produkvarian) on update cascade on delete restrict,
	harga_modal int,
	jumlah numeric(10,2),
	selisih numeric(10,2),
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying
); 

ALTER TABLE toko_griyanaura.tr_penyesuaiangudangdetail
ADD UNIQUE (id_gudang, id_penyesuaiangudang, kode_produkvarian);

ALTER TABLE toko_griyanaura.tr_penyesuaiangudangdetail
alter column id_gudang set not null,
alter column id_penyesuaiangudang set not null,
alter column kode_produkvarian set not null;

alter table toko_griyanaura.tr_penyesuaiangudang
	add column is_valid boolean default false;