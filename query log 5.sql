create table toko_griyanaura.tr_pindahgudang(
	id_pindahgudang serial primary key,
	tanggal timestamp(0) default current_timestamp(0),
	from_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict,
	to_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict,
	keterangan varchar(200),
	catatan varchar(400),
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying	
);

create table toko_griyanaura.tr_pindahgudangdetail (
	id_pindahgudangdetail serial primary key,
	id_pindahgudang int references toko_griyanaura.tr_pindahgudang(id_pindahgudang) on update cascade on delete restrict,
	kode_produkvarian varchar(50) references toko_griyanaura.ms_produkvarian(kode_produkvarian) on update cascade on delete restrict,
	jumlah numeric(10,2),
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying
);