create sequence toko_griyanaura.tr_pindahgudang_transaksi_no_seq
	start 1
	no cycle;

create or replace function toko_griyanaura.get_pindahgudang_transaksi_no()
	returns text
as $$
declare 
	p_val int; 
	v_ret text;
begin
	p_val := nextval('toko_griyanaura.tr_pindahgudang_transaksi_no_seq'::regclass);
	v_ret := 'WT' || lpad(p_val::text, 6, '0');

	return v_ret;
end;
$$
language plpgsql;

alter table toko_griyanaura.tr_pindahgudang add column transaksi_no varchar(50) unique default toko_griyanaura.get_pindahgudang_transaksi_no();