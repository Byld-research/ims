<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw rows for fixtures. Real orders are created through PurchaseOrderService.
 *
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PO-TEST-'.fake()->unique()->numerify('#####'),
            'supplier_id' => Supplier::factory(),
            'site_id' => Site::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function status(PurchaseOrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
