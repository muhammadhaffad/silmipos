CREATE SEQUENCE toko_griyanaura.tr_penjualanretur_seq;

CREATE OR REPLACE FUNCTION toko_griyanaura.f_calckembaliandanareturpenjualan(_kode_transaksi character varying)
  RETURNS integer AS
$BODY$
declare
	_retur record;
	_dibayarkan int;
	_kembaliandanaretur int;
	_kembaliandana int;
begin
	select * into _retur from toko_griyanaura.tr_penjualanretur where transaksi_no = _kode_transaksi;
	_dibayarkan := (select coalesce(sum(nominal),0) from toko_griyanaura.tr_penjualanalokasipembayaran where id_penjualaninvoice = _retur.id_penjualan);
	_kembaliandanaretur := (select coalesce(sum(kembaliandana),0) from toko_griyanaura.tr_penjualanretur where transaksi_no <> _kode_transaksi and id_penjualan = _retur.id_penjualan);
	_kembaliandana := least(_retur.grandtotal, _dibayarkan - _kembaliandanaretur);
	update toko_griyanaura.tr_penjualanretur set kembaliandana = _kembaliandana where id_penjualanretur = _retur.id_penjualanretur;
	return _kembaliandana;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.f_calckembaliandanareturpenjualan(character varying)
  OWNER TO postgres;

CREATE OR REPLACE FUNCTION toko_griyanaura.f_getsisapembayaranpenjualan(_kode_pembayaran character varying)
  RETURNS integer AS
$BODY$
declare
	_pembayaran record;
	_dipakaibayar int;
	_darisisaretur int;
	_dipakairefund int;
begin
	select * into _pembayaran from toko_griyanaura.tr_penjualanpembayaran where transaksi_no = _kode_pembayaran;
	_dipakaibayar := (
			select coalesce(sum(nominal),0) 
			from toko_griyanaura.tr_penjualanalokasipembayaran ppb
			where id_penjualanpembayaran = _pembayaran.id_penjualanpembayaran
			);
	_dipakairefund := (
			select coalesce(sum(nominal),0) 
			from toko_griyanaura.tr_penjualanrefunddetail prd
			where id_penjualanpembayaran = _pembayaran.id_penjualanpembayaran
			);
	_darisisaretur := (
			select coalesce(sum(nominal),0) 
			from toko_griyanaura.tr_penjualanreturalokasikembaliandana ppb
			where id_penjualanpembayaran = _pembayaran.id_penjualanpembayaran
			);
	return _pembayaran.nominal + _darisisaretur - _dipakairefund - _dipakaibayar;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.f_getsisapembayaran(character varying)
  OWNER TO postgres;

alter table toko_griyanaura.tr_penjualanreturdetail add column id_gudang int references toko_griyanaura.lv_gudang(id_gudang) on update cascade on delete restrict;