@php($d = \App\Support\DemoData::imposition())
<x-dl::imposition :jobs="$d['jobs']" :planos="$d['planos']" :gripper="$d['gripper']" :margin="$d['margin']" :gap="$d['gap']" :qty="10000" />
