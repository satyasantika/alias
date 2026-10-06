<?php

namespace App\Exceptions;

use RuntimeException;

/** Pembatas laju per host cek-tujuan penuh; job dilepas untuk dicoba lagi. */
class HostSibuk extends RuntimeException {}
