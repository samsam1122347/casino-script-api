<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Same channel/payload as {@see CrashGameTick}, but broadcasts in-process
 * (no queue worker) when CRASH_BROADCAST_IMMEDIATE=true.
 */
class CrashGameTickImmediate extends CrashGameTick implements ShouldBroadcastNow {}
