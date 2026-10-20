<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reviewlens:prune-imports')->daily();
