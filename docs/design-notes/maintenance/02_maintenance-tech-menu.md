# Technical Plan: Maintenance Menu

Menu Maintenance yang direncanakan: empat kelompok ditambah Report, dan layar mana masuk fase mana.

## Isi catatan

1. Modul punya empat kelompok: Preventive Maintenance, Breakdown Maintenance, Improvement dan Master Data. Kelompok kecil Report ada di bawahnya.
2. Preventive Maintenance Order (PMO) dan Breakdown Maintenance Order (BMO) adalah dua pintu menu ke satu desain Maintenance Order dan satu tabel. Tiap pintu punya prefiks nomor sendiri.
3. Menu hanya menampilkan layar yang sudah ada. Sebuah layar muncul pada hari fasenya selesai.
4. Akses mengikuti modul MAINT. Siapa pun yang boleh masuk Production boleh melapor masalah. Keputusan butuh hak approve.

## Istilah dan rincian

- **Preventive Maintenance**: Preventive Plan, PM Calendar, Preventive Maintenance Order (PMO), Daily Check, Meter Readings. Dikirim di Fase 2.
- **Breakdown Maintenance**: Maintenance Request, Breakdown Maintenance Order (BMO), My Work untuk teknisi. Dikirim di Fase 3.
- **Improvement**: Improvement Proposal dan Improvement Review. Order improvement (IMO) dibuka dari usulannya. Dikirim di Fase 4.
- **Master Data**: Asset Maintenance Profile, Maintenance Codes, dan tautan ke Master Mesin. Dikirim di Fase 1.
- **Report**: Maintenance Order Report, Machine Reliability, PM Compliance. Dikirim bersama fase yang datanya mereka baca.
