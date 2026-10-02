<?php

namespace Assistant\Contracts;

// Perintah = metric yang MENGUBAH sesuatu atas permintaan si penanya (mis. memasang widget di papannya), bukan menjawab angka.
// Kata pemicunya menang atas pertanyaan angka: "lepas widget sales today" adalah perintah papan, bukan pertanyaan "sales today".
interface Command extends Metric {}
