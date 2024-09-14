create table toko_griyanaura.lv_gudang (
	id_gudang serial primary key,
	nama varchar(100)
);

create table toko_griyanaura.ms_produkvarianharga (
	id_produkvarianharga serial primary key,
	kode_produkvarian varchar(50) references toko_griyanaura.ms_produkvarian(kode_produkvarian) on update cascade on delete restrict,
	nama varchar(50),
	hargajual int,
	hargabeli int,
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying
);

create table toko_griyanaura.ms_persediaan (
	id_persediaan serial primary key,
	id_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict,
	kode_produkvarian varchar(50) references toko_griyanaura.ms_produkvarian(kode_produkvarian) on update cascade on delete restrict,
	minstok numeric(10,2) default 0,
	stok numeric(10,2),
	default_varianharga int references toko_griyanaura.ms_produkvarianharga(id_produkvarianharga) on update cascade on delete restrict,
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying
);

alter table toko_griyanaura.lv_gudang 
	add column inserted_at timestamp(0) without time zone DEFAULT now(),
	add column inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	add column updated_at timestamp(0) without time zone DEFAULT now(),
	add column updated_by character varying(50) DEFAULT 'postgres'::character varying

insert into toko_griyanaura.lv_gudang (nama) values ('Pusat')

insert into toko_griyanaura.ms_produkvarianharga (kode_produkvarian, nama, hargajual, hargabeli) 
select kode_produkvarian, 'Reguler', hargajual, default_hargabeli
from toko_griyanaura.ms_produkvarian 

insert into toko_griyanaura.ms_persediaan (id_gudang, kode_produkvarian, minstok, stok, default_varianharga)
select gdg.id_gudang, pv.kode_produkvarian, pv.minstok, pv.stok, pvh.id_produkvarianharga
from toko_griyanaura.ms_produkvarian pv
join (select * from toko_griyanaura.lv_gudang limit 1) gdg on true
join (select * from toko_griyanaura.ms_produkvarianharga where nama='Reguler') pvh on pvh.kode_produkvarian = pv.kode_produkvarian

ALTER TABLE toko_griyanaura.ms_persediaan RENAME TO ms_produkpersediaan

alter table toko_griyanaura.ms_produkpersediaan drop column minstok;

create table toko_griyanaura.lv_varianharga (
	id_varianharga serial primary key,
	nama varchar(50),
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying
);

insert into toko_griyanaura.lv_varianharga (nama) values ('Reguler'),('Cacat');

alter table toko_griyanaura.ms_produkvarianharga add column 
	id_varianharga int references toko_griyanaura.lv_varianharga(id_varianharga) on update cascade on delete restrict
;

update toko_griyanaura.ms_produkvarianharga set id_varianharga = vh.id_varianharga 
from 
(select id_varianharga from toko_griyanaura.lv_varianharga where nama = 'Reguler') as vh;

alter table toko_griyanaura.ms_produkvarianharga drop column nama;