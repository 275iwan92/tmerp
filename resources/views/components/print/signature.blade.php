{{-- company = teks bebas di atas garis tanda tangan. companyPpn (bool) = tulis nama
     perusahaan lewat x-print.company-name, ikut pilihan kop Non PPN/PPN dokumen. --}}
@props(['role', 'company' => null, 'companyPpn' => null, 'name' => null, 'sub' => null])
<div class="print-sign-cell">
    @if ($companyPpn !== null)
        <div class="co"><x-print.company-name :ppn="$companyPpn" /></div>
    @elseif ($company)
        <div class="co">{{ $company }}</div>
    @endif
    <div class="line"></div>
    <div class="rol">{{ $role }}</div>
    @if ($name)<div class="nm">{{ $name }}</div>@endif
    @if ($sub)<div class="sub">{{ $sub }}</div>@endif
</div>
