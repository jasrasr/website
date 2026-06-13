<?php declare(strict_types=1);
/**
 * Filename: frontlines/team_roster.php
 * Revision : 2.4.1
 * Description : Frontlines 2026 roster defaults plus JSON/CSV persistence helpers.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-09
 * Modified Date : 2026-06-12
 * Changelog :
 * 1.0.0 Initial roster from Frontlines 2026 team screenshots
 * 2.0.0 Added editable JSON storage and CSV export with gender/grade fields
 * 2.1.0 Added CSV role column for Leader, Sponsor, and Youth rows
 * 2.2.0 Reordered CSV columns and added youth gender probability guesses
 * 2.3.0 Load gender/grade defaults from tracked roster CSV import
 * 2.4.0 Add Chris Banto, Vivien Banto, Claudia Banto, Olga Soljaga to defaults
 * 2.4.1 Rename C.J Fitzgerald to Connor "CJ" Fitzgerald
 */

const FRONTLINES_ROSTER_JSON = __DIR__ . '/data/team-roster.json';
const FRONTLINES_ROSTER_CSV = __DIR__ . '/data/team-roster.csv';
const FRONTLINES_ROSTER_DEFAULTS_CSV = __DIR__ . '/team-roster-defaults.csv';

function frontlinesRosterDefaultData(): array
{
    return frontlinesApplyRosterCsvDefaults([
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
                ['Chai Beard', 'Connor "CJ" Fitzgerald', 'Lydia Poelking', 'Gage Defendorf', 'Christine Brinkley', 'Andrew Zerminski', 'Lily Hearst', 'Kesi Van De Pitte', 'Jeremiah Zaccardelli', 'Ava Taylor', 'Lincoln Misch', 'Hannah Luczywo', 'Cameron Campo', 'Hannah Gosnell', 'Savannah Cruse', 'Brandon Lockhart', 'Lucia Hallier'],
                'Angelica Prindle'
            ),
            'team-dark-green' => frontlinesRosterTeam(
                ['Hannah Defendorf', 'Aaron Archacki', 'Simonne Benoit'],
                ['Dante Arena', 'Alexis Brody', 'Johnny Zacardelli', 'Halle Luczywo', 'Wendy Sutton', 'Brayden Howard', 'Veronica Cimino', 'Pelumi Lawal', 'Aubrey Render', 'Amya Thomas', 'Noah Brown', 'Mercy Beard', 'Noah Brown', 'Hannah Busch', 'Caleb Duncan', 'Nana Kwasi Asafu Adjaye', 'David Powell', 'Chris Banto'],
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
                ['Clark Defendorf', 'Abby Sutton', 'Aubrey Garcia', 'Logan Gray', 'Hosea Cole', 'Ava Busch', 'Anthony Knapik', 'Eian Sykes', 'Kendall Lavinder', 'Luca Tinter', 'Carter Edlind', 'Ava Hannah', 'Josiah Wolfe', 'William Hanson', 'Ashton Frey', 'Joy Wolfenbarger', 'Vivien Banto'],
                'Mary Shreve/Grace Valenti'
            ),
            'team-purple' => frontlinesRosterTeam(
                ['Amy Gustafson', 'Julie Bay', 'Traci Schimpf'],
                ['Nathan Sutton', 'Scarlett Sherman', 'Navara Trimble', 'Ava Weatherbie', 'Hudson Defendorf', 'Misha Tinter', 'Beckit Cole', 'Haddon Elvington', 'Kylie Perrico', 'Felicity Kawczak', 'Lucas Phillips', 'Derek Keller', 'Sami Eimers', 'Violetta Meuti', 'Cara Cole', 'Robby Smith', 'Claudia Banto'],
                'John Chrisopulos/Jeff Eicher'
            ),
            'team-smoke' => frontlinesRosterTeam(
                ['Linda Valenti', 'Daniel Diaz', 'Kim Brody'],
                ['Morayoifeoluwa Oguntoyinbo', 'Asher Beard', 'Mia Londrico', 'Roman Bossman', 'Elle Hileman', 'Marco Gillota', 'Ellie Peak', 'Francesca Tinter', 'Vivian Rowell', 'Raiden Ballinger', 'Timi Lawal', 'Eli Graham', 'Callan Stevens', 'Hosanna Beard', 'Darci Duncan', 'Elizabeth Seiter', 'Emma Jacobson', 'Olga Soljaga'],
                'Alysia Hanson'
            ),
        ],
    ]);
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
            'leaders' => frontlinesMergeRosterPeopleDefaults(
                frontlinesNormalizeRosterPeople($team['leaders'] ?? []),
                frontlinesNormalizeRosterPeople($defaultTeam['leaders'] ?? [])
            ),
            'members' => frontlinesMergeRosterPeopleDefaults(
                frontlinesNormalizeRosterPeople($team['members'] ?? []),
                frontlinesNormalizeRosterPeople($defaultTeam['members'] ?? [])
            ),
            'sponsor' => trim((string) ($team['sponsor'] ?? '')) ?: trim((string) ($defaultTeam['sponsor'] ?? '')),
        ];
    }

    return $normalized;
}

function frontlinesApplyRosterCsvDefaults(array $data): array
{
    if (!is_file(FRONTLINES_ROSTER_DEFAULTS_CSV)) {
        return $data;
    }

    $handle = fopen(FRONTLINES_ROSTER_DEFAULTS_CSV, 'rb');
    if ($handle === false) {
        return $data;
    }

    $headers = fgetcsv($handle);
    if (!is_array($headers)) {
        fclose($handle);
        return $data;
    }

    $teamIdsByName = [];
    foreach (scoreboardDefaultData()['teams'] as $team) {
        $teamIdsByName[$team['name'] . ' Team'] = $team['id'];
    }

    while (($values = fgetcsv($handle)) !== false) {
        $row = array_combine($headers, $values);
        if (!is_array($row)) {
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));
        $teamId = $teamIdsByName[trim((string) ($row['team_name'] ?? ''))] ?? null;
        $role = strtolower(trim((string) ($row['role'] ?? '')));
        if ($name === '' || $teamId === null || !isset($data['teams'][$teamId])) {
            continue;
        }

        $gender = frontlinesCleanRosterCsvValue((string) ($row['gender'] ?? ''));
        $grade = frontlinesCleanRosterCsvValue((string) ($row['grade'] ?? ''));

        if ($role === 'leader') {
            $data['teams'][$teamId]['leaders'] = frontlinesUpsertRosterPersonDefaults($data['teams'][$teamId]['leaders'], $name, $gender, $grade);
        } elseif ($role === 'youth') {
            $data['teams'][$teamId]['members'] = frontlinesUpsertRosterPersonDefaults($data['teams'][$teamId]['members'], $name, $gender, $grade);
        } elseif ($role === 'sponsor') {
            $data['teams'][$teamId]['sponsor'] = $name;
        }
    }

    fclose($handle);
    return $data;
}

function frontlinesUpsertRosterPersonDefaults(array $people, string $name, string $gender, string $grade): array
{
    foreach ($people as $index => $person) {
        if (strcasecmp((string) ($person['name'] ?? ''), $name) !== 0) {
            continue;
        }

        $people[$index] = frontlinesRosterPerson(
            (string) ($person['name'] ?? $name),
            frontlinesCleanRosterCsvValue((string) ($person['gender'] ?? '')) ?: $gender,
            frontlinesCleanRosterCsvValue((string) ($person['grade'] ?? '')) ?: $grade
        );
        return $people;
    }

    $people[] = frontlinesRosterPerson($name, $gender, $grade);
    return $people;
}

function frontlinesMergeRosterPeopleDefaults(array $people, array $defaults): array
{
    $defaultsByName = [];
    foreach ($defaults as $person) {
        $defaultsByName[strtolower((string) ($person['name'] ?? ''))] = $person;
    }

    foreach ($people as $index => $person) {
        $default = $defaultsByName[strtolower((string) ($person['name'] ?? ''))] ?? null;
        if ($default === null) {
            continue;
        }

        $people[$index] = frontlinesRosterPerson(
            (string) ($person['name'] ?? ''),
            frontlinesCleanRosterCsvValue((string) ($person['gender'] ?? '')) ?: frontlinesCleanRosterCsvValue((string) ($default['gender'] ?? '')),
            frontlinesCleanRosterCsvValue((string) ($person['grade'] ?? '')) ?: frontlinesCleanRosterCsvValue((string) ($default['grade'] ?? ''))
        );
    }

    return $people;
}

function frontlinesCleanRosterCsvValue(string $value): string
{
    $value = trim($value);
    return strtoupper($value) === 'N/A' ? '' : $value;
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

    fputcsv($handle, frontlinesRosterCsvHeaders());
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
        foreach ($teamRoster['leaders'] ?? [] as $person) {
            $rows[] = frontlinesRosterCsvRow($person, $teamName, 'Leader');
        }

        $sponsor = trim((string) ($teamRoster['sponsor'] ?? ''));
        if ($sponsor !== '') {
            $rows[] = [$sponsor, $teamName, 'Sponsor', '', '', ''];
        }

        foreach ($teamRoster['members'] ?? [] as $person) {
            $rows[] = frontlinesRosterCsvRow($person, $teamName, 'Youth');
        }
    }

    return $rows;
}

function frontlinesRosterCsvHeaders(): array
{
    return ['name', 'team_name', 'role', 'gender', 'gender_probability', 'grade'];
}

function frontlinesRosterCsvRow(array $person, string $teamName, string $role): array
{
    $gender = trim((string) ($person['gender'] ?? ''));
    $probability = '';
    if ($role === 'Youth' && $gender === '') {
        [$gender, $probability] = frontlinesGuessGender((string) ($person['name'] ?? ''));
    }

    return [
        $person['name'] ?? '',
        $teamName,
        $role,
        $role === 'Youth' ? $gender : '',
        $role === 'Youth' ? $probability : '',
        $person['grade'] ?? '',
    ];
}

function frontlinesGuessGender(string $name): array
{
    $firstName = strtolower((string) preg_replace('/[^A-Za-z].*$/', '', trim($name)));
    $guesses = [
        'abbie' => ['F', '95%'],
        'abby' => ['F', '95%'],
        'addison' => ['F', '78%'],
        'addi' => ['F', '85%'],
        'adele' => ['F', '99%'],
        'aidan' => ['M', '92%'],
        'aidrian' => ['M', '80%'],
        'alden' => ['M', '86%'],
        'alex' => ['M', '65%'],
        'alexis' => ['F', '82%'],
        'amya' => ['F', '92%'],
        'andrew' => ['M', '99%'],
        'anthony' => ['M', '99%'],
        'ariel' => ['F', '72%'],
        'arianna' => ['F', '99%'],
        'arlen' => ['M', '72%'],
        'asher' => ['M', '99%'],
        'ashton' => ['M', '78%'],
        'aubrey' => ['F', '86%'],
        'aubrie' => ['F', '96%'],
        'ava' => ['F', '99%'],
        'ayla' => ['F', '99%'],
        'becket' => ['M', '95%'],
        'beckit' => ['M', '85%'],
        'bella' => ['F', '99%'],
        'bence' => ['M', '95%'],
        'brennan' => ['M', '94%'],
        'brandon' => ['M', '99%'],
        'brayden' => ['M', '98%'],
        'brianna' => ['F', '99%'],
        'bryce' => ['M', '95%'],
        'cadence' => ['F', '78%'],
        'caleb' => ['M', '99%'],
        'callan' => ['M', '75%'],
        'cameron' => ['M', '72%'],
        'cara' => ['F', '99%'],
        'carter' => ['M', '94%'],
        'cassidy' => ['F', '88%'],
        'catalina' => ['F', '99%'],
        'chai' => ['M', '60%'],
        'charlotte' => ['F', '99%'],
        'chloe' => ['F', '99%'],
        'christine' => ['F', '99%'],
        'clark' => ['M', '99%'],
        'cole' => ['M', '96%'],
        'colin' => ['M', '99%'],
        'danica' => ['F', '99%'],
        'dante' => ['M', '99%'],
        'darci' => ['F', '96%'],
        'david' => ['M', '99%'],
        'derek' => ['M', '99%'],
        'elijah' => ['M', '99%'],
        'elise' => ['F', '98%'],
        'elizabeth' => ['F', '99%'],
        'elle' => ['F', '98%'],
        'ellie' => ['F', '99%'],
        'ella' => ['F', '99%'],
        'emma' => ['F', '99%'],
        'eian' => ['M', '90%'],
        'ethan' => ['M', '99%'],
        'eva' => ['F', '99%'],
        'ezekiel' => ['M', '99%'],
        'ezra' => ['M', '96%'],
        'faith' => ['F', '99%'],
        'felicity' => ['F', '99%'],
        'francesca' => ['F', '99%'],
        'gage' => ['M', '98%'],
        'gili' => ['F', '60%'],
        'grace' => ['F', '99%'],
        'grady' => ['M', '98%'],
        'grayson' => ['M', '88%'],
        'grey' => ['M', '65%'],
        'haddon' => ['M', '85%'],
        'halle' => ['F', '94%'],
        'hannah' => ['F', '99%'],
        'helena' => ['F', '99%'],
        'hope' => ['F', '99%'],
        'hosanna' => ['F', '96%'],
        'hosea' => ['M', '98%'],
        'hudson' => ['M', '99%'],
        'isaac' => ['M', '99%'],
        'jacob' => ['M', '99%'],
        'jake' => ['M', '99%'],
        'james' => ['M', '99%'],
        'jayda' => ['F', '98%'],
        'jennifer' => ['F', '99%'],
        'jeremiah' => ['M', '99%'],
        'johnny' => ['M', '99%'],
        'jonas' => ['M', '99%'],
        'jonathan' => ['M', '99%'],
        'jordan' => ['M', '65%'],
        'joseph' => ['M', '99%'],
        'josiah' => ['M', '99%'],
        'joy' => ['F', '98%'],
        'justice' => ['M', '58%'],
        'joshua' => ['M', '99%'],
        'julianna' => ['F', '99%'],
        'kendall' => ['F', '72%'],
        'kealynn' => ['F', '94%'],
        'kesi' => ['F', '65%'],
        'kristian' => ['M', '88%'],
        'kylie' => ['F', '99%'],
        'lauren' => ['F', '98%'],
        'lillian' => ['F', '99%'],
        'lily' => ['F', '99%'],
        'lincoln' => ['M', '96%'],
        'lindsey' => ['F', '84%'],
        'logan' => ['M', '82%'],
        'lucas' => ['M', '99%'],
        'lucia' => ['F', '99%'],
        'luca' => ['M', '92%'],
        'lydia' => ['F', '99%'],
        'maame' => ['F', '80%'],
        'malachi' => ['M', '99%'],
        'marco' => ['M', '99%'],
        'mari' => ['F', '88%'],
        'maranata' => ['F', '70%'],
        'mary' => ['F', '99%'],
        'mercy' => ['F', '92%'],
        'mia' => ['F', '99%'],
        'micah' => ['M', '85%'],
        'misha' => ['M', '55%'],
        'morayoifeoluwa' => ['F', '55%'],
        'nana' => ['M', '55%'],
        'naomi' => ['F', '99%'],
        'naomie' => ['F', '98%'],
        'nathan' => ['M', '99%'],
        'navara' => ['F', '70%'],
        'niko' => ['M', '95%'],
        'noah' => ['M', '99%'],
        'olivia' => ['F', '99%'],
        'owen' => ['M', '99%'],
        'pelumi' => ['F', '55%'],
        'quinn' => ['M', '58%'],
        'raegan' => ['F', '85%'],
        'raiden' => ['M', '85%'],
        'robby' => ['M', '99%'],
        'robert' => ['M', '99%'],
        'roman' => ['M', '99%'],
        'ryan' => ['M', '88%'],
        'sami' => ['F', '62%'],
        'samantha' => ['F', '99%'],
        'sarah' => ['F', '99%'],
        'savannah' => ['F', '99%'],
        'sawyer' => ['M', '72%'],
        'scarlett' => ['F', '99%'],
        'selah' => ['F', '92%'],
        'skyasha' => ['F', '70%'],
        'sophia' => ['F', '99%'],
        'stella' => ['F', '99%'],
        'steven' => ['M', '99%'],
        'timi' => ['M', '60%'],
        'trevor' => ['M', '99%'],
        'tristan' => ['M', '86%'],
        'vanessa' => ['F', '99%'],
        'veronica' => ['F', '99%'],
        'vivian' => ['F', '99%'],
        'violetta' => ['F', '99%'],
        'vlad' => ['M', '99%'],
        'wendy' => ['F', '99%'],
        'wesley' => ['M', '99%'],
        'weston' => ['M', '99%'],
        'william' => ['M', '99%'],
        'zeke' => ['M', '99%'],
    ];

    return $guesses[$firstName] ?? ['', ''];
}
