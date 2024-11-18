alter table toko_griyanaura.tr_penjualanalokasipembayaran add column kembalian int;

-- Function: toko_griyanaura.f_getsisapembayaranpenjualan(character varying)

-- DROP FUNCTION toko_griyanaura.f_getsisapembayaranpenjualan(character varying);

CREATE OR REPLACE FUNCTION toko_griyanaura.f_getsisapembayaranpenjualan(_kode_pembayaran character varying)
  RETURNS integer AS
$BODY$
declare
	_pembayaran record;
	_dipakaibayar int;
	_darisisaretur int;
	_dipakairefund int;
	_dikembalikan int;
begin
	select * into _pembayaran from toko_griyanaura.tr_penjualanpembayaran where transaksi_no = _kode_pembayaran;
	_dipakaibayar := (
			select coalesce(sum(nominal),0) 
			from toko_griyanaura.tr_penjualanalokasipembayaran ppb
			where id_penjualanpembayaran = _pembayaran.id_penjualanpembayaran
			);
	_dikembalikan := (
			select coalesce(sum(kembalian),0) 
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
	return _pembayaran.nominal + _darisisaretur - _dipakairefund - _dipakaibayar - _dikembalikan;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.f_getsisapembayaranpenjualan(character varying)
  OWNER TO postgres;