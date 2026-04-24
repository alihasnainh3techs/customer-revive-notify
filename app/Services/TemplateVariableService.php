<?php

namespace App\Services;

use App\Models\Campaign;

class TemplateVariableService
{
    /**
     * Replace placeholders in a string with actual data.
     */
    public function replace(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            // Ensure we handle null values by converting to empty string
            $content = str_replace("[$key]", $value ?? '', $content);
        }

        // Final cleanup: Remove any remaining [tags] that weren't replaced
        return preg_replace('/\[.*?\]/', '', $content);
    }

    /**
     * Prepare the mapping of variables to values.
     */
    public function getMapping(Campaign $campaign, $customer, $shopData): array
    {
        $isDiscount = $campaign->campaign_type === 'discount';

        return [
            'first_name'       => $customer['firstName'] ?? '',
            'last_name'        => $customer['lastName'] ?? '',
            'email'            => $customer['email'] ?? '',
            'phone'            => $customer['phone'] ?? '',
            'shop_name'        => $shopData['name'] ?? '',
            'shop_url'         => $shopData['url'] ?? '',
            'shop_email'       => $shopData['email'] ?? '',
            // Discount variables (Only if type is discount)
            'discount_code'    => $isDiscount ? ($campaign->discount_code ?? '') : '',
            'discount_amount'  => $isDiscount ? ($this->formatDiscount($campaign)) : '',
            'discount_expiry'  => $isDiscount ? ($campaign->custom_validity ?? '') : '',
            'discount_link'    => $isDiscount ? ($this->generateDiscountLink($shopData['url'], $campaign->discount_code)) : '',
            // Order details
            'last_order_date'  => $customer['lastOrder']['createdAt'] ?? '',
            'total_spent'      => $customer['totalSpent'] ?? '0.00',
        ];
    }

    private function formatDiscount(Campaign $campaign): string
    {
        $rules = $campaign->discount_rules;
        if ($campaign->discount_type === 'percentage_discount') {
            return ($rules['percentage']['value'] ?? '0') . '%';
        }
        return ($rules['fixed']['value'] ?? '0');
    }

    private function generateDiscountLink($shopUrl, $code): string
    {
        if (!$shopUrl || !$code) return '';
        return rtrim($shopUrl, '/') . '/discount/' . $code;
    }
}
