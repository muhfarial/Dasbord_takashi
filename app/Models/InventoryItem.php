<?php

namespace App\Models;

class InventoryItem
{
    public string $id;
    public string $sku;
    public string $name;
    public string $category;
    public string $unit;
    public string $abcClass;
    public float $safetyStock;
    public float $optimalStock;
    public float $standardCost;
    public float $currentStock;
    public ?int $maxDwell;

    public function __construct(array $attrs = [])
    {
        $this->id = $attrs['id'] ?? uniqid('item-');
        $this->sku = $attrs['sku'] ?? '';
        $this->name = $attrs['name'] ?? '';
        $this->category = $attrs['category'] ?? '';
        $this->unit = $attrs['unit'] ?? 'kg';
        $this->abcClass = $attrs['abc_class'] ?? 'B';
        $this->safetyStock = (float)($attrs['min_safety_stock'] ?? 10);
        $this->optimalStock = (float)($attrs['optimal_stock'] ?? 50);
        $this->standardCost = (float)($attrs['standard_cost'] ?? 0);
        $this->currentStock = (float)($attrs['current_stock'] ?? 0);
        $this->maxDwell = $attrs['max_dwell_hours'] ?? null;
    }

    public function getStatus(): string
    {
        if ($this->currentStock <= 0) return 'EMPTY';
        if ($this->currentStock <= $this->safetyStock * 0.75) return 'CRITICAL';
        if ($this->currentStock <= $this->safetyStock) return 'WARNING';
        return 'SAFE';
    }

    public function getValuation(): float
    {
        return $this->currentStock * $this->standardCost;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'abcClass' => $this->abcClass,
            'safetyStock' => $this->safetyStock,
            'optimalStock' => $this->optimalStock,
            'standardCost' => $this->standardCost,
            'currentStock' => $this->currentStock,
            'status' => $this->getStatus(),
            'valuation' => $this->getValuation(),
            'maxDwell' => $this->maxDwell,
        ];
    }
}
