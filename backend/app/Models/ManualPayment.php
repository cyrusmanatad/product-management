<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVendor;
use Illuminate\Database\Eloquent\Model;

class ManualPayment extends Model
{
    use BelongsToVendor;
}
