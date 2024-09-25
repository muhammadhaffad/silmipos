select 
  * 
from 
  "toko_griyanaura"."ms_produkpersediaan" 
  inner join (
    select 
      id_gudang, 
      nama as nama_gudang 
    from 
      toko_griyanaura.lv_gudang
  ) as gdg on "toko_griyanaura"."ms_produkpersediaan"."id_gudang" = "gdg"."id_gudang" 
  left join (
    select 
      id_penyesuaiangudangdetail, 
      id_penyesuaiangudang, 
      kode_produkvarian, 
      jumlah as jumlah_penyesuaian, 
      selisih as selisih_stokfisik, 
      harga_modal, 
      id_gudang as id_gudangpenyesuaian 
    from 
      toko_griyanaura.tr_penyesuaiangudangdetail
  ) as pnyd on "pnyd"."kode_produkvarian" = "toko_griyanaura"."ms_produkpersediaan"."kode_produkvarian" 
where 
  "toko_griyanaura"."ms_produkpersediaan"."kode_produkvarian" in (
    'SRG130ABL', 'SRG130ABM', 'SRG130ABS', 
    'SRK127ABL', 'SRK127ABM', 'SRK127ABS', 
    'SRK127MRL', 'SRK127MRM', 'SRK127MRS'
  );
  
ALTER TABLE toko_griyanaura.tr_penyesuaiangudangdetail
ADD UNIQUE (id_gudang, id_penyesuaiangudang, kode_produkvarian);

ALTER TABLE toko_griyanaura.tr_penyesuaiangudangdetail
alter column id_gudang set not null,
alter column id_penyesuaiangudang set not null,
alter column kode_produkvarian set not null;

alter table toko_griyanaura.tr_penyesuaiangudang
	add column is_valid boolean default false;