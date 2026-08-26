<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Same envelope as {@see CrashGameOpsPulse}, without queue. */
class CrashGameOpsPulseImmediate extends CrashGameOpsPulse implements ShouldBroadcastNow {}
