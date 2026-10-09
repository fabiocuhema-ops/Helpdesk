<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sector_id', 'name', 'address'])]
class MunicipalLocation extends Model {}
