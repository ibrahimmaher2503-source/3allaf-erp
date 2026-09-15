<?php
namespace App\Modules\Catalog\Models;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
abstract class CatalogLookup extends Model {
    protected $fillable = ['code','name_ar','name_en','status','sort_order'];
    protected static function booted(): void { static::updating(function (self $model): void { if ($model->isDirty('status') && $model->status === 'inactive' && $model->products()->exists()) throw new InvalidArgumentException(__('This lookup value cannot be deactivated while products use it.')); }); }
    public function products() { return $this->hasMany(Product::class, $this->getForeignKey()); }
    public function getForeignKey(): string { return match (static::class) { AgeLabel::class => 'age_label_id', Character::class => 'character_id', Colour::class => 'colour_id', Gender::class => 'gender_id', default => throw new \LogicException('Unsupported catalog lookup model'), }; }
}
