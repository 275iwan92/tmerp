<?php

namespace App\Services\Docs;

use App\Mail\DocShareMail;
use App\Models\Sys\SysConstant;
use App\Models\Sys\SysDocEmail;
use App\Models\Sys\SysDocShare;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Throwable;

// Print & Email dokumen (objek DevLib x-dl::doc-share). Snapshot HTML dari browser disimpan apa adanya kecuali script/event
// handler (dibuang di sini, bukan dipercaya dari klien) karena halaman /d/{token} terbuka tanpa login.
class DocShareService
{
    public function __construct(private string $user) {}

    public function snapshot(array $d): SysDocShare
    {
        $html = self::sanitize($d['html']);
        $hash = hash('sha256', $html);
        $same = SysDocShare::where('doc_type', $d['doc_type'])->where('doc_id', $d['doc_id'] ?? null)->where('html_hash', $hash)->latest('id_share')->first();
        if ($same && ! $same->isExpired()) {
            return $same;
        }
        $days = (int) SysConstant::number('DOC_SHARE_DAYS', 0);

        return SysDocShare::create(['token' => bin2hex(random_bytes(16)), 'doc_type' => $d['doc_type'], 'doc_id' => $d['doc_id'] ?? null,
            'doc_no' => $d['doc_no'] ?? null, 'title' => $d['title'], 'html' => $html, 'html_hash' => $hash,
            'expires_at' => $days > 0 ? now()->addDays($days) : null, 'created_by' => $this->user, 'updated_by' => $this->user]);
    }

    public function email(SysDocShare $share, array $d, ?UploadedFile $image, ?UploadedFile $pdf, ?array $sender = null): SysDocEmail
    {
        $log = new SysDocEmail(['share_id' => $share->id_share, 'to_list' => implode(', ', $d['to']), 'cc_list' => implode(', ', $d['cc']) ?: null,
            'bcc_list' => implode(', ', $d['bcc']) ?: null, 'subject' => $d['subject'], 'message' => $d['message'], 'with_link' => $d['with_link'],
            'with_image' => $image !== null, 'with_pdf' => $pdf !== null, 'created_by' => $this->user, 'updated_by' => $this->user]);
        try {
            Mail::to($d['to'])->cc($d['cc'])->bcc($d['bcc'])->send(new DocShareMail($share, $d['subject'], $d['message'], $d['with_link'],
                $image?->get(), $pdf ? [$pdf->get(), self::fileName($share).'.pdf'] : null, $sender));
            $log->status = 'SENT';
        } catch (Throwable $e) {
            $log->status = 'FAILED';
            $log->error = mb_substr($e->getMessage(), 0, 2000);
        }
        $log->save();

        return $log;
    }

    // "a@x.com, b@y.com; c@z" -> daftar alamat (validasi format di route).
    public static function addresses(?string $list): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $list))));
    }

    public static function fileName(SysDocShare $share): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $share->title.($share->doc_no ? '_'.$share->doc_no : '')), '_');
    }

    public static function sanitize(string $html): string
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="dl-share-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        foreach (iterator_to_array($x->query('//script|//iframe|//object|//embed|//link|//meta|//base|//form//input[@type="hidden"]')) as $n) {
            $n->parentNode->removeChild($n);
        }
        foreach (iterator_to_array($x->query('//*')) as $el) {
            /** @var DOMElement $el */
            foreach (iterator_to_array($el->attributes) as $a) {
                $v = strtolower(preg_replace('/\s+/', '', $a->value));
                if (str_starts_with(strtolower($a->name), 'on') || (in_array($a->name, ['href', 'src', 'action', 'formaction', 'xlink:href'], true)
                    && (str_starts_with($v, 'javascript:') || (str_starts_with($v, 'data:') && ! str_starts_with($v, 'data:image/'))))) {
                    $el->removeAttribute($a->name);
                }
            }
            if (in_array($el->tagName, ['form'], true)) {
                $el->removeAttribute('action');
            }
        }
        $root = $x->query('//*[@id="dl-share-root"]')->item(0);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $dom->saveHTML($c);
        }

        return $out;
    }
}
