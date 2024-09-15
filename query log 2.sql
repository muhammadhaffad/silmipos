create table toko_griyanaura.ms_produkharga (
	id_produkharga serial primary key,
	id_produk int references toko_griyanaura.ms_produk(id_produk) on update cascade on delete restrict,
	id_varianharga int references toko_griyanaura.lv_varianharga(id_varianharga) on update cascade on delete restrict,
	inserted_at timestamp(0) without time zone DEFAULT now(),
	inserted_by character varying(50) DEFAULT 'postgres'::character varying,
	updated_at timestamp(0) without time zone DEFAULT now(),
	updated_by character varying(50) DEFAULT 'postgres'::character varying
);

insert into toko_griyanaura.ms_produkharga (id_produk, id_varianharga) 
select id_produk, 1 from toko_griyanaura.ms_produk;

alter table toko_griyanaura.ms_produkvarianharga add column id_produkharga int references toko_griyanaura.ms_produkharga(id_produkharga) on update cascade on delete restrict;


update toko_griyanaura.ms_produkvarianharga
set id_produkharga = pvh2.id_produkharga
from (select pv.kode_produkvarian, ph.id_produkharga from toko_griyanaura.ms_produkvarianharga pvhr
join toko_griyanaura.ms_produkvarian pv using (kode_produkvarian)
join toko_griyanaura.ms_produkharga ph on pv.id_produk = ph.id_produk and pvhr.id_varianharga = ph.id_varianharga) as pvh2
where pvh2.kode_produkvarian = toko_griyanaura.ms_produkvarianharga.kode_produkvarian;

alter table toko_griyanaura.ms_produkvarianharga drop column id_varianharga;