<?php
/**
 * Filename: DemoData.php
 * Revision: 1.2.0
 * Description: Repeatable fictional businesses, customers and paid-service reviews.
 * Author: Jason Lamb with ChatGPT
 * Created: 2026-11-18
 * Modified: 2026-11-18
 * Changelog: 1.2.0 - Seed ten businesses, ten customers and ten reviews.
 */
declare(strict_types=1);

use KingdomConnections\JsonStore;

final class DemoData
{
    public static function populate(string $storage, Identity $identity, KingdomDirectory $directory, string $superId): array
    {
        // A persistent plan lets interrupted runs resume without duplicate accounts or reviews.
        $path = $storage . '/demo-seed.json';
        $plan = JsonStore::update($path, static function (array $records): array {
            if ($records) return $records;
            foreach (self::businesses() as $username => $business) {
                $records[$username] = ['role' => 'business', 'name' => $business['employee'], 'password' => bin2hex(random_bytes(12))];
            }
            foreach (self::customers() as $username => $name) {
                $records[$username] = ['role' => 'customer', 'name' => $name, 'password' => bin2hex(random_bytes(12))];
            }
            return $records;
        });
        $accounts = [];
        foreach ($identity->accounts() as $account) $accounts[$account['username']] = $account;
        // Check all collisions before creating any accounts. Never adopt an unrelated user.
        foreach ($plan as $username => $entry) {
            if (isset($accounts[$username]) && ($accounts[$username]['role'] !== $entry['role'] || !password_verify($entry['password'], $accounts[$username]['hash']))) {
                throw new InvalidArgumentException('Demo username already in use: ' . $username . '. Resolve the conflict before loading demo data.');
            }
        }
        $ids = [];
        foreach ($plan as $username => $entry) {
            $ids[$username] = $accounts[$username]['id'] ?? $identity->account('', $username, $entry['name'], $entry['role'], $entry['password']);
        }
        $customerNames = array_keys(self::customers());
        $profiles = $directory->customers();
        foreach (self::customers() as $username => $name) {
            if (!isset($profiles[$ids[$username]])) $directory->saveCustomer($ids[$username], ['name' => $name, 'email' => $username . '@example.test']);
        }
        $reviewText = [
            'The leak was repaired promptly, and the work area was left clean.',
            'Careful craftsmanship and helpful communication throughout the cabinet project.',
            'Our air conditioning is working well. The technician explained the repair clearly.',
            'The yard looks great, and the team arrived when promised.',
            'The new paint looks good. Scheduling needed one follow-up call.',
            'A thorough cleaning with close attention to the kitchen and bathrooms.',
            'The roof repair was explained clearly and completed within the estimate.',
            'The brake service was completed on time, with a clear explanation of the parts.',
            'Patient help getting our home computers and Wi-Fi working again.',
            'Friendly service and delicious food for our family gathering.',
        ];
        $index = 0;
        foreach (self::businesses() as $username => $fields) {
            $business = self::ownedBusiness($directory, $ids[$username]);
            $id = $business['id'] ?? $directory->save($fields, $ids[$username], true);
            $customer = $ids[$customerNames[$index]];
            $reference = 'DEMO-REVIEW-' . ($index + 1);
            $key = self::payment($directory, $id, $customer, $reference, $fields['service'], $superId, true);
            $business = self::ownedBusiness($directory, $ids[$username]);
            if (!in_array($key, array_column($business['ratings'], 'payment'), true)) {
                $directory->serviceAction('review', ['id' => $id, 'key' => $key, 'stars' => (string) [5, 5, 4, 5, 3, 4, 5, 4, 5, 5][$index], 'comment' => 'Demo review — fictional service and simulated payment. ' . $reviewText[$index]], $customer, false);
            }
            ++$index;
        }
        // Keep one eligible review and one pending payment available to demonstrate the workflow.
        $first = self::ownedBusiness($directory, $ids['demo-trades']);
        self::payment($directory, $first['id'], $ids['demo-customer'], 'DEMO-SETTLED', 'Demo plumbing visit', $superId, true);
        self::payment($directory, $first['id'], $ids['demo-customer'], 'DEMO-PENDING', 'Demo electrical inspection', $superId, false);
        return $plan;
    }

    private static function ownedBusiness(KingdomDirectory $directory, string $owner): ?array
    {
        foreach ($directory->all() as $business) {
            if (($business['owner'] ?? '') === $owner) return $business;
        }
        return null;
    }

    private static function payment(KingdomDirectory $directory, string $id, string $customer, string $reference, string $service, string $super, bool $verify): string
    {
        foreach ($directory->all() as $business) {
            if ($business['id'] !== $id) continue;
            foreach ($business['payments'] ?? [] as $key => $payment) {
                if ($payment['reference'] === $reference) return $key;
            }
        }
        $directory->serviceAction('claim', ['id' => $id, 'reference' => $reference, 'service' => $service], $customer, false);
        foreach ($directory->all() as $business) {
            if ($business['id'] !== $id) continue;
            foreach ($business['payments'] as $key => $payment) {
                if ($payment['reference'] !== $reference) continue;
                if ($verify) $directory->serviceAction('verify', ['id' => $id, 'key' => $key, 'evidence' => 'checked'], $super, true);
                return $key;
            }
        }
        throw new RuntimeException('Demo payment could not be saved.');
    }

    private static function customers(): array
    {
        return ['demo-customer' => 'Demo Avery Morgan', 'demo-customer-02' => 'Demo Jordan Ellis', 'demo-customer-03' => 'Demo Taylor Brooks', 'demo-customer-04' => 'Demo Casey Bennett', 'demo-customer-05' => 'Demo Riley Parker', 'demo-customer-06' => 'Demo Morgan Hayes', 'demo-customer-07' => 'Demo Cameron Reed', 'demo-customer-08' => 'Demo Quinn Foster', 'demo-customer-09' => 'Demo Jamie Collins', 'demo-customer-10' => 'Demo Drew Walker'];
    }

    private static function businesses(): array
    {
        $rows = [
            ['demo-trades', 'Faithful Home Services', 'plumbing, electrical', 'Sam Mercer', 'Leak repair and electrical safety checks', 'Serving homeowners with repairs, maintenance and careful troubleshooting.'],
            ['demo-wood', 'Good Neighbor Woodworking', 'carpentry, cabinetry', 'Alex Sutton', 'Custom cabinet installation', 'Custom shelving, cabinetry and trim work for homes and small offices.'],
            ['demo-hvac', 'Grace Heating & Cooling', 'hvac, heating, air conditioning', 'Robin Lane', 'Air conditioning repair', 'Seasonal tune-ups, heating repairs and cooling system maintenance.'],
            ['demo-landscape', 'Living Waters Landscaping', 'landscaping, lawn care', 'Dylan Carter', 'Lawn and garden maintenance', 'Lawn care, garden beds and practical landscape maintenance.'],
            ['demo-paint', 'New Creation Painting', 'painting, drywall', 'Peyton Shaw', 'Interior room painting', 'Interior painting, drywall repairs and tidy surface preparation.'],
            ['demo-cleaning', 'Shining Light Cleaning', 'cleaning, housekeeping', 'Skyler Grant', 'Deep house cleaning', 'Residential deep cleans and recurring housekeeping visits.'],
            ['demo-roof', 'Cornerstone Roofing', 'roofing, gutters', 'Emerson Blake', 'Roof leak and gutter repair', 'Roof repairs, gutter maintenance and storm-damage assessments.'],
            ['demo-auto', 'Honest Way Auto Care', 'auto repair, brakes', 'Rowan Price', 'Brake inspection and repair', 'Routine vehicle service, brake work and diagnostic inspections.'],
            ['demo-tech', 'Servant Heart Technology', 'it support, networking', 'Finley Ross', 'Home Wi-Fi troubleshooting', 'Computer troubleshooting, home networking and small business IT help.'],
            ['demo-catering', 'Daily Bread Catering', 'catering, baking', 'Harper Wells', 'Family event catering', 'Family gatherings, community events and small celebration catering.'],
        ];
        $businesses = [];
        foreach ($rows as $index => [$username, $name, $skills, $employee, $service, $about]) {
            $businesses[$username] = ['name' => 'Demo ' . $name, 'skills' => $skills, 'employee' => $employee, 'service' => $service, 'about' => 'Fictional demonstration business. ' . $about, 'testimony' => 'Our Christian faith calls us to serve our neighbors with honesty, patience and care. We aim to reflect those values in our ' . explode(',', $skills)[0] . ' work.', 'address' => 'Demo service area — Greater Cleveland, Ohio', 'phone' => sprintf('216-555-%04d', 100 + $index), 'email' => $username . '@example.test'];
        }
        return $businesses;
    }
}
