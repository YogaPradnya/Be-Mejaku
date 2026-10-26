export function rupiah(amount) {
  return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
}
