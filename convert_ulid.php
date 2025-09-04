<?php
require 'vendor/autoload.php';
use Symfony\Component\Uid\Ulid;

$hex = '01991426-d393-9d8c-b776-7d8c6d75675a';
$ulid = Ulid::fromString($hex);
echo 'ULID base32: ' . $ulid->toBase32() . PHP_EOL;
echo 'ULID hex: ' . $ulid->toHex() . PHP_EOL;
?>
