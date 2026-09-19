"""Ekstrak file gaji mingguan HR (.xlsb) ke JSON untuk `php artisan hrm:import-hr-sample`.

PHP (PhpSpreadsheet) tidak punya reader .xlsb, jadi dibaca pakai pyxlsb.
Output berisi angka gaji -> ditulis ke storage/app/private/hr (di-gitignore),
JANGAN di-commit.

    python scripts/hr/extract_payroll_xlsb.py "docs/HR/9. GAJI ... WEEKLY 1660.xlsb"
"""
import json
import os
import re
import sys
from datetime import date, timedelta

from pyxlsb import open_workbook

OUT_DIR = os.path.join('storage', 'app', 'private', 'hr')


def xl_date(v):
    return (date(1899, 12, 30) + timedelta(days=int(v))).isoformat() if isinstance(v, (int, float)) else None


def xl_time(v):
    if not isinstance(v, (int, float)) or v <= 0:
        return None
    m = round(v * 1440)
    return f'{m // 60:02d}:{m % 60:02d}'


def num(v):
    return float(v) if isinstance(v, (int, float)) else 0.0


def sheet(wb, name):
    with wb.get_sheet(name) as sh:
        return [[c.v for c in r] for r in sh.rows()]


def pad(r, n):
    return r + [None] * (n - len(r))


def main(path):
    batch = re.search(r'(\d+)\.xlsb$', path).group(1)
    with open_workbook(path) as wb:
        ta = sheet(wb, 'TA')
        dates = [xl_date(v) for v in ta[5][2:16:2]]
        attendance = []
        for r in ta[6:]:
            r = pad(r, 40)
            if not isinstance(r[1], str) or not r[1].strip():
                continue
            days = []
            for i, d in enumerate(dates):
                days.append({
                    'date': d,
                    'in': xl_time(r[2 + i * 2]),
                    'out': xl_time(r[3 + i * 2]),
                    'hours': num(r[16 + i]),
                    'normative': num(r[24 + i]),
                })
            attendance.append({'name': r[1].strip(), 'days': days})

        prop = sheet(wb, 'PROPOSAL')
        proposal = []
        for r in prop[15:]:
            r = pad(r, 23)
            if not isinstance(r[1], str) or not r[1].strip():
                continue
            proposal.append({
                'name': r[1].strip(), 'join': xl_date(r[2]), 'division': r[7], 'title': (r[8] or '').strip() or None,
                'pokok': num(r[9]), 'keahlian': num(r[10]), 'bonus_pagi': num(r[11]), 'bonus_hadir': num(r[12]),
                'loyalitas': num(r[13]), 'prestasi': num(r[14]), 'tj_jabatan': num(r[18]), 'lembur_per_jam': num(r[20]),
            })

        rekap = []
        for r in sheet(wb, 'REKAP ABSEN')[6:]:
            r = pad(r, 42)
            if not isinstance(r[4], str) or not r[4].strip():
                continue
            rekap.append({
                'name': r[4].strip(), 'sakit': num(r[6]), 'ijin': num(r[7]), 'alpha': num(r[8]), 'cuti': num(r[9]),
                'terlambat_x': num(r[18]), 'lembur_jam': num(r[20]), 'uang_roti_x': num(r[23]),
                'pokok': num(r[24]), 'keahlian': num(r[25]), 'bonus_pagi': num(r[26]), 'bonus_hadir': num(r[27]),
                'loyalitas': num(r[28]), 'prestasi': num(r[29]), 'gaji_diterima': num(r[30]), 'potongan': num(r[35]),
                'lembur': num(r[36]) + num(r[37]) + num(r[38]) + num(r[39]), 'uang_roti': num(r[40]), 'lain': num(r[41]),
            })

        tot = sheet(wb, 'TOTAL')
        period_label = tot[0][3]
        totals = [{'name': r[2].strip(), 'total': num(r[3])} for r in tot[3:] if len(r) > 3 and isinstance(r[2], str) and r[2].strip()]

    out = {
        'batch': batch, 'source': os.path.basename(path), 'period_label': period_label,
        'period_start': dates[0], 'period_end': dates[-1],
        'attendance': attendance, 'proposal': proposal, 'rekap': rekap, 'totals': totals,
    }
    os.makedirs(OUT_DIR, exist_ok=True)
    target = os.path.join(OUT_DIR, f'payroll_{batch}.json')
    with open(target, 'w', encoding='utf-8') as fh:
        json.dump(out, fh, ensure_ascii=False, indent=1)
    print(f'{target}: {len(attendance)} presensi, {len(proposal)} proposal, {len(rekap)} rekap, {len(totals)} total')


if __name__ == '__main__':
    main(sys.argv[1])
