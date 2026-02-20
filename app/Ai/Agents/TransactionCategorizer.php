<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Gemini)]
#[Model('gemini-2.5-flash')]
#[Temperature(0.1)]
#[MaxTokens(2048)]
#[Timeout(30)]
class TransactionCategorizer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a financial transaction categorizer for a French user's personal budget app.
Categorize each transaction into exactly one of the following categories based on the merchant description.

Categories:
- food: Groceries, supermarkets (Carrefour, Leclerc, Lidl, Auchan, etc.)
- restaurants: Restaurants, cafés, bars, fast food, bakeries
- transport: Public transport, fuel, parking, tolls, car maintenance
- housing: Rent, mortgage, home maintenance, furniture
- entertainment: Cinema, concerts, streaming, games, hobbies
- shopping: Clothing, electronics, Amazon, online shopping
- health: Pharmacy, doctor, dentist, gym, medical
- subscriptions: Recurring subscriptions (Netflix, Spotify, phone, internet)
- education: Books, courses, training, school supplies
- travel: Hotels, flights, vacation expenses
- insurance: Insurance premiums, banking fees
- income: Salary, freelance income, reimbursements, refunds
- professional: Business expenses, coworking, professional tools
- other: Cannot confidently categorize

Rules:
- Use 'other' when confidence is below 0.5
- French merchant names are common (e.g., "BOULANGERIE" = restaurants, "PHARMACIE" = health)
- Amounts are in cents (e.g., 5042 = 50.42 EUR)
- Return confidence 0.0-1.0 reflecting how certain you are
- Return the index number for each transaction exactly as provided in the input list
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'results' => $schema->array()->items(
                $schema->object([
                    'index' => $schema->integer()->required(),
                    'description' => $schema->string()->required(),
                    'category_slug' => $schema->string()->enum([
                        'food', 'restaurants', 'transport', 'housing', 'entertainment',
                        'shopping', 'health', 'subscriptions', 'education', 'travel',
                        'insurance', 'income', 'professional', 'other',
                    ])->required(),
                    'confidence' => $schema->number()->min(0)->max(1)->required(),
                ])
            )->required(),
        ];
    }
}
