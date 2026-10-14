/* REMARK Tujuan: langkah bersama skrip video PPIC (buka menu Manufacture > Planning > layar). Dipakai oleh: tests/e2e/video/*.mjs. Status: aktif. */
export async function openScreen(r, label, caption) {
  await r.caption(caption || 'Buka menu Manufacture di sebelah kiri.', 1800);
  await r.click({text: '^Manufacture'}, {after: 900});
  await r.caption('Pilih Planning.', 1200);
  await r.click({text: '^Planning'}, {after: 900});
  await r.caption(`Pilih ${label}.`, 1200);
  await r.click({text: '^' + label}, {after: 2500});
}
