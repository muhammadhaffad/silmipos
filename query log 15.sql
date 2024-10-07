-- Function: toko_griyanaura.f_getsisatagihan(character varying)

-- DROP FUNCTION toko_griyanaura.f_getsisatagihan(character varying);

CREATE OR REPLACE FUNCTION toko_griyanaura.f_getsisatagihan(_kode_pembelian character varying)
  RETURNS integer AS
$BODY$
declare
	_pembelian record;
	_diretur int;
	_transaksi record;
	_sisatagihan int;
	_nominal int;
	_dibayar int;
	_grandtotal int;
begin
	select * into _pembelian from toko_griyanaura.tr_pembelian where transaksi_no = _kode_pembelian;
	_sisatagihan := _pembelian.grandtotal;
	_grandtotal := _pembelian.grandtotal;
	for _transaksi in (
		with x as (
			select *, SUM(CASE WHEN jenis = nextjenis THEN 0 ELSE 1 END) OVER (ORDER BY tanggal asc) AS group_id from (
				select *, lag(jenis) over (order by tanggal asc) as nextjenis from (
					select 
						coalesce(grandtotal, 0) as nominal,
						'R' as jenis,
						tanggal
					from toko_griyanaura.tr_pembelianretur 
					where id_pembelian = _pembelian.id_pembelian
					union all
					select 
						coalesce(nominal,0) as nominal,
						'P' as jenis,
						tanggal
					from toko_griyanaura.tr_pembelianalokasipembayaran 
					where id_pembelianinvoice = _pembelian.id_pembelian
				) as z order by tanggal asc
			) as y
		)
		select group_id, jenis, sum(nominal) as total, coalesce(lag(sum(nominal)) over (order by group_id),0) as prev_total from x group by group_id, jenis order by group_id
	)
	loop
		if (_transaksi.jenis = 'R') then
			_grandtotal := least(_grandtotal - _transaksi.prev_total, _grandtotal - _transaksi.total);
			_sisatagihan := _grandtotal;
		end if;
		if (_transaksi.jenis = 'P') then
			_sisatagihan := _grandtotal - _transaksi.total;
		end if;
	end loop;
	return _sisatagihan;
end;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION toko_griyanaura.f_getsisatagihan(character varying)
  OWNER TO postgres;
