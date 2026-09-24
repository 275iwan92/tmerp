<?php

// Alat developer: render satu halaman sebagai pengguna tertentu TANPA login di browser (password tidak boleh
// diketik di browser). Hasil disimpan ke public/_t.html -> buka http://<host>/_t.html (host sama = aset ikut).
// Pakai (PowerShell, dari folder tmerp):  php tools/render-as.php <login> <path> [host]
//   php tools/render-as.php johiwan /v2                    (mockup.test = database dummy ddt*, bawaan)
//   php tools/render-as.php carrij /laporan/eksekutif mockup.test
// Hapus public/_t.html setelah selesai. Status 302 = pengguna dialihkan (mis. wajib ganti password).
[$script, $login, $path, $host] = array_pad($argv, 4, null);
if (! $login || ! $path) {
    fwrite(STDERR, "Pakai: php tools/render-as.php <login> <path> [host=mockup.test]\n");
    exit(1);
}
$_SERVER['HTTP_HOST'] = $host ?: 'mockup.test';

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('http://'.$_SERVER['HTTP_HOST'].$path);
$app->instance('request', $request);
$kernel->bootstrap();

$user = App\Models\Sys\SecUser::where('login', $login)->firstOrFail();
$app['auth']->guard('web')->setUser($user);
$response = $kernel->handle($request);
file_put_contents(__DIR__.'/../public/_t.html', $response->getContent());

echo $response->getStatusCode().($response->headers->get('Location') ? ' -> '.$response->headers->get('Location') : '')
    .' | db='.config('database.connections.tm2sys.database').' | simpan: public/_t.html'.PHP_EOL;
