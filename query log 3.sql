create sequence toko_griyanaura.ms_produkvarian_kode_produkvarian_seq
	start 1
	no cycle;

create or replace function toko_griyanaura.get_kodeprodukvarian()
	returns text
as $$
declare 
	p_val int; 
	v_ret text;
begin
	p_val := nextval('toko_griyanaura.ms_produkvarian_kode_produkvarian_seq'::regclass);
	v_ret := 'P' || lpad(p_val::text, 6, '0');

	return v_ret;
end;
$$
language plpgsql;

alter table toko_griyanaura.ms_produkvarian alter column kode_produkvarian set default toko_griyanaura.get_kodeprodukvarian();