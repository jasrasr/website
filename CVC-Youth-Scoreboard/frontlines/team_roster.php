<?php declare(strict_types=1);
/**
 * Filename: frontlines/team_roster.php
 * Revision : 2.0.0
 * Description : Frontlines 2026 roster defaults plus JSON/CSV persistence helpers.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-09
 * Modified Date : 2026-06-09
 * Changelog :
 * 1.0.0 Initial roster from Frontlines 2026 team screenshots
 * 2.0.0 Added editable JSON storage and CSV export with gender/grade fields
 */

const FRONTLINES_ROSTER_JSON = __DIR__ . '/data/team-roster.json';
const FRONTLINES_ROSTER_CSV = __DIR__ . '/data/team-roster.csv';

function frontlinesRosterDefaultData(): array
{
    return [
        'updatedAt' => null,
        'teams' => [
            'team-red' => frontlinesRosterTeam(
                ['Jon Sutton', 'Kris Sutton', 'Jordyn Hauser'],
                ['Hope Sherman', 'Aidan Winkler', 'Adele Mansfield', 'Eva Carson', 'Alden Brinkley', 'Jacob Kasmer', 'Grey Hileman', 'Nathan Bakai', 'Ava Chrisopulos', 'Niko Djordjevich', 'Joy Heindel', 'Ayla Londrico', 'Mary Ella Sizemore', 'Tristan Szeszak', 'Arlen Manduzic', 'Stella Springer', 'Jacob Sizemore'],
                'Skyview Staff'
            ),
            'team-maroon' => frontlinesRosterTeam(
                ['Jason Lamb', 'Cort Cable', 'Ryan Dale'],
                ['Jake Archaki', 'Owen Jenks', 'Charlotte Eimers', 'Grace Johnson', 'Aidrian Stevens', 'Faith Miliano', 'James Kutsar', 'Maame Asafu Adjaye', 'Francesca Cruse', 'Andrew Hanson', 'Robert Koenig', 'Ryan Zerminski', 'Elise Clement', 'Brennan Render', 'Vanessa Klinesmith', 'Jillian Bolczak', 'Julianna Hernandez'],
                'Skyview Staff'
            ),
            'team-orange' => frontlinesRosterTeam(
                ['Dave Gutting', 'Brad Luczywo', 'Josh Hudak'],
                ['Elijah Zaccardelli', 'Alex Lamb', 'Logan Valenti', 'Cadence Kasmer', 'Aubrie Hamilton', 'Ella Rowles', 'Colin Bay', 'Grace Heindel', 'Wesley Phillips', 'Helena Zelenka', 'Andrew Nary', 'Elizabeth Poelking', 'Ezra Howard', 'Lily Smith', 'Nathan Walter', 'Charlotte Intihar'],
                'Skyview Staff'
            ),
            'team-yellow' => frontlinesRosterTeam(
                ['Kyle Gustafson', 'Monica Hatton', 'Kaylee Rauch'],
                ['Malachi Lafontaine', 'Bella McKeever', 'Raegan Smith', 'Skyasha Riggins', 'Jennifer Bolczak', 'Sophia Hetsler', 'Catalina Serrano', 'Jonas Tretter', 'Micah Zaccardelli', 'Naomi Knapik', 'Samantha Lavinder', 'Arianna Szesak', 'Bence Hornyak', 'Kealynn Trimble', 'Joshua Tinter', 'Isaac Hearst', 'Jonathan Sizemore'],
                'Skyview Staff'
            ),
            'team-light-green' => frontlinesRosterTeam(
                ['Joelle Cole', 'Dan Sahli', 'Inna Cherevko'],
                ['Chai Beard', 'C.J Fitzgerald', 'Lydia Poelking', 'Gage Defendorf', 'Christine Brinkley', 'Andrew Zerminski', 'Lily Hearst', 'Kesi Van De Pitte', 'Jeremiah Zaccardelli', 'Ava Taylor', 'Lincoln Misch', 'Hannah Luczywo', 'Cameron Campo', 'Hannah Gosnell', 'Savannah Cruse', 'Brandon Lockhart', 'Lucia Hallier'],
                'Angelica Prindle'
            ),
            'team-dark-green' => frontlinesRosterTeam(
                ['Hannah Defendorf', 'Aaron Archacki', 'Simonne Benoit'],
                ['Dante Arena', 'Alexis Brody', 'Johnny Zacardelli', 'Halle Luczywo', 'Wendy Sutton', 'Brayden Howard', 'Veronica Cimino', 'Pelumi Lawal', 'Aubrey Render', 'Amya Thomas', 'Noah Brown', 'Mercy Beard', 'Noah Brown', 'Hannah Busch', 'Caleb Duncan', 'Nana Kwasi Asafu Adjaye', 'David Powell'],
                'Vinny Malone'
            ),
            'team-light-blue' => frontlinesRosterTeam(
                ['Diane Archaki', 'Matt Howard', 'Jenny Hayes'],
                ['Josiah Didea', 'Sophia Siley', 'Sarah Poelking', 'Addi Fankhauser', 'Sophia Cozmyk', 'Grayson Kieschnik', 'Chloe Cutright', 'Sawyer Schneider', 'Abbie Carson', 'Zeke Siley', 'Nathan Mansfield', 'Lily Rutti', 'Grady Hoover', 'Addison Frey', 'Andrew McGregor', 'Gili Kawczak', 'Isaac Lockhart'],
                'Paula Londrico'
            ),
            'team-royal-blue' => frontlinesRosterTeam(
                ['Jeanette Cable', 'Viveka Jenks', 'Sam Londrico'],
                ['Cole Hileman', 'Mari Hopkins', 'Weston Kiner', 'Stella Siley', 'James Carson', 'Brianna Steward', 'Cassidy Rubin', 'Joseph Bolczak', 'Lauren Sahli', 'Danica DAmico', 'Steven Castro', 'Jordan Hamilton', 'Olivia Cole', 'Ariel Piasecki', 'Daniel Bakai', 'Bobbie Kawczak', 'Jayda Boone'],
                'Talia Cole'
            ),
            'team-navy' => frontlinesRosterTeam(
                ['Vince Pozar', 'Emma Hamilton', 'Andy Bay'],
                ['Bryce Defendorf', 'Naomie Siley', 'Maranata Gomez', 'Addi Tretter', 'Elizabeth Chepelev', 'Trevor Riha', 'Sophia Lantvit', 'Justice Sexton', 'Selah Scialabba', 'Vlad Piasecki', 'Lindsey Fitzgerald', 'Stella Randels', 'Ethan Duncan', 'Lillian Kawczak', 'Elijah Lantvit', 'Quinn Patton', 'Kristian Van De Pitte'],
                'Marcus Cutright'
            ),
            'team-pink' => frontlinesRosterTeam(
                ['Fred Defendorf', 'Rene Piasecki', 'Jeanette Fitzgerald'],
                ['Clark Defendorf', 'Abby Sutton', 'Aubrey Garcia', 'Logan Gray', 'Hosea Cole', 'Ava Busch', 'Anthony Knapik', 'Eian Sykes', 'Kendall Lavinder', 'Luca Tinter', 'Carter Edlind', 'Ava Hannah', 'Josiah Wolfe', 'William Hanson', 'Ashton Frey', 'Joy Wolfenbarger'],
                'Mary Shreve/Grace Valenti'
            ),
            'team-purple' => frontlinesRosterTeam(
                ['Amy Gustafson', 'Julie Bay', 'Traci Schimpf'],
                ['Nathan Sutton', 'Scarlett Sherman', 'Navara Trimble', 'Ava Weatherbie', 'Hudson Defendorf', 'Misha Tinter', 'Beckit Cole', 'Haddon Elvington', 'Kylie Perrico', 'Felicity Kawczak', 'Lucas Phillips', 'Derek Keller', 'Sami Eimers', 'Violetta Meuti', 'Cara Cole', 'Robby Smith'],
                'John Chrisopulos/Jeff Eicher'
            ),
            'team-smoke' => frontlinesRosterTeam(
                ['Linda Valenti', 'Daniel Diaz', 'Kim Brody'],
                ['Morayoifeoluwa Oguntoyinbo', 'Asher Beard', 'Mia Londrico', 'Roman Bossman', 'Elle Hileman', 'Marco Gillota', 'Ellie Peak', 'Francesca Tinter', 'Vivian Rowell', 'Raiden Ballinger', 'Timi Lawal', 'Eli Graham', 'Callan Stevens', 'Hosanna Beard', 'Darci Duncan', 'Elizabeth Seiter', 'Emma Jacobson'],
                'Alysia Hanson'
            ),
        ],
    ];
}

function frontlinesRosterTeam(array $leaders, array $members, string $sponsor): array
{
    return [
        'leaders' => array_map(static fn(string $name): array => frontlinesRosterPerson($name), $leaders),
        'members' => array_map(static fn(string $name): array => frontlinesRosterPerson($name), $members),
        'sponsor' => $sponsor,
    ];
}

function frontlinesRosterPerson(string $name, string $gender = '', string $grade = ''): array
{
    return [
        'name' => $name,
        'gender' => $gender,
        'grade' => $grade,
    ];
}

function ensureFrontlinesRosterFiles(): void
{
    $directory = dirname(FRONTLINES_ROSTER_JSON);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    if (!is_file(FRONTLINES_ROSTER_JSON)) {
        $data = frontlinesRosterDefaultData();
        saveFrontlinesRosterData($data);
    } elseif (!is_file(FRONTLINES_ROSTER_CSV)) {
        writeFrontlinesRosterCsv(readFrontlinesRosterData());
    }
}

function readFrontlinesRosterData(): array
{
    if (!is_file(FRONTLINES_ROSTER_JSON)) {
        $data = frontlinesRosterDefaultData();
        saveFrontlinesRosterData($data);
        return $data;
    }

    $decoded = json_decode(file_get_contents(FRONTLINES_ROSTER_JSON) ?: '', true);
    return is_array($decoded) ? frontlinesNormalizeRosterData($decoded) : frontlinesRosterDefaultData();
}

function saveFrontlinesRosterData(array $data): void
{
    $directory = dirname(FRONTLINES_ROSTER_JSON);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $data = frontlinesNormalizeRosterData($data);
    $data['updatedAt'] = gmdate('c');
    file_put_contents(FRONTLINES_ROSTER_JSON, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    writeFrontlinesRosterCsv($data);
}

function frontlinesNormalizeRosterData(array $data): array
{
    $defaults = frontlinesRosterDefaultData();
    $normalized = [
        'updatedAt' => $data['updatedAt'] ?? null,
        'teams' => [],
    ];

    foreach ($defaults['teams'] as $teamId => $defaultTeam) {
        $team = is_array($data['teams'][$teamId] ?? null) ? $data['teams'][$teamId] : $defaultTeam;
        $normalized['teams'][$teamId] = [
            'leaders' => frontlinesNormalizeRosterPeople($team['leaders'] ?? []),
            'members' => frontlinesNormalizeRosterPeople($team['members'] ?? []),
            'sponsor' => trim((string) ($team['sponsor'] ?? '')),
        ];
    }

    return $normalized;
}

function frontlinesNormalizeRosterPeople(array $people): array
{
    $normalized = [];
    foreach ($people as $person) {
        if (is_string($person)) {
            $person = ['name' => $person];
        }
        if (!is_array($person)) {
            continue;
        }

        $name = trim((string) ($person['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $normalized[] = frontlinesRosterPerson(
            $name,
            trim((string) ($person['gender'] ?? '')),
            trim((string) ($person['grade'] ?? ''))
        );
    }

    return $normalized;
}

function writeFrontlinesRosterCsv(array $data): void
{
    $handle = fopen(FRONTLINES_ROSTER_CSV, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Unable to write roster CSV.');
    }

    fputcsv($handle, ['member_leader_name', 'team_name', 'gender', 'grade']);
    foreach (frontlinesRosterCsvRows($data) as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);
}

function frontlinesRosterCsvRows(array $data): array
{
    $scoreboard = scoreboardDefaultData();
    $teamNames = [];
    foreach ($scoreboard['teams'] as $team) {
        $teamNames[$team['id']] = $team['name'] . ' Team';
    }

    $rows = [];
    foreach ($data['teams'] ?? [] as $teamId => $teamRoster) {
        $teamName = $teamNames[$teamId] ?? $teamId;
        foreach (['leaders', 'members'] as $group) {
            foreach ($teamRoster[$group] ?? [] as $person) {
                $rows[] = [
                    $person['name'] ?? '',
                    $teamName,
                    $person['gender'] ?? '',
                    $person['grade'] ?? '',
                ];
            }
        }
    }

    return $rows;
}
