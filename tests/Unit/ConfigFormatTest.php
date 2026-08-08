<?php

namespace Tests\Unit;

use App\Services\Config\Formats;
use App\Services\Config\IniFormat;
use App\Services\Config\PalworldIniFormat;
use App\Services\Config\PropertiesFormat;
use App\Services\Config\YamlFormat;
use PHPUnit\Framework\TestCase;

/**
 * The config editor's whole claim is that it changes what you asked for and
 * nothing else.
 *
 * That is not a thing you can eyeball, so every format is given a realistic
 * file with comments, odd spacing and keys the panel has never heard of, asked
 * to change exactly one value, and then checked line by line: the changed line
 * must be the only line that differs, and every other byte in the file has to
 * come back identical. A config editor that silently drops the four lines an
 * operator added by hand is worse than no config editor.
 */
class ConfigFormatTest extends TestCase
{
    /**
     * Assert that applying $values to $raw changed exactly the lines listed in
     * $expectChanged and left every other line byte identical.
     *
     * @param  array<int,int>  $expectChanged  line numbers, zero based
     */
    private function assertOnlyTheseLinesChanged(string $raw, string $out, array $expectChanged): void
    {
        $before = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $after = preg_split("/\r\n|\n|\r/", $out) ?: [];

        $changed = [];
        foreach ($before as $i => $line) {
            if (! array_key_exists($i, $after) || $after[$i] !== $line) {
                $changed[] = $i;
            }
        }

        $this->assertSame($expectChanged, $changed, 'Lines that changed were not the lines expected.');
        $this->assertSame(count($before), count($after), 'The file gained or lost a line.');
    }

    // ------------------------------------------------------------ properties

    private function serverProperties(): string
    {
        // Shaped like the file Minecraft actually writes: a generated header,
        // alphabetical keys, escaped section signs in the MOTD, plus two things
        // the game did not put there and this must not touch.
        return implode("\n", [
            '#Minecraft server properties',
            '#Thu Aug 07 12:00:00 UTC 2026',
            'allow-flight=false',
            'allow-nether=true',
            'difficulty=easy',
            'enable-command-block=false',
            'level-name=world',
            'max-players=20',
            'motd=A §cMinecraft§r Server',
            'online-mode=true',
            '',
            '# added by hand, do not remove',
            'my-plugin.threshold = 42',
            'pvp=true',
            'rcon.password=hunter2',
            'view-distance=10',
            'white-list=false',
            '',
        ]);
    }

    public function test_properties_round_trips_with_one_value_changed(): void
    {
        $raw = $this->serverProperties();
        $format = new PropertiesFormat;

        $out = $format->apply($raw, ['view-distance' => '12']);

        // view-distance is line 15 counting the two header comments.
        $this->assertOnlyTheseLinesChanged($raw, $out, [15]);
        $this->assertStringContainsString("\nview-distance=12\n", $out);
        $this->assertStringContainsString("\n# added by hand, do not remove\n", $out);
        $this->assertStringContainsString("\nmy-plugin.threshold = 42\n", $out);
    }

    public function test_properties_parses_escapes_and_odd_separators(): void
    {
        $values = (new PropertiesFormat)->parse($this->serverProperties());

        $this->assertSame('A §cMinecraft§r Server', $values['motd']);
        // "key = value" with spaces is legal and the value is not "= 42".
        $this->assertSame('42', $values['my-plugin.threshold']);
        $this->assertSame('hunter2', $values['rcon.password']);
        $this->assertArrayNotHasKey('#Minecraft server properties', $values);
    }

    public function test_properties_writes_escapes_back_the_way_java_reads_them(): void
    {
        $raw = $this->serverProperties();
        $out = (new PropertiesFormat)->apply($raw, ['motd' => 'A §aGreen§r Server']);

        // Written as \uXXXX rather than raw UTF-8 on purpose. Properties.load
        // decodes those escapes whatever the file's encoding is, so an escaped
        // section sign is correct on every Minecraft version there has ever
        // been, where a raw one is only correct on the versions that read the
        // file as UTF-8.
        $this->assertStringContainsString('motd=A \\u00A7aGreen\\u00A7r Server', $out);
        // And straight back out again, unchanged.
        $this->assertSame('A §aGreen§r Server', (new PropertiesFormat)->parse($out)['motd']);
    }

    public function test_properties_appends_a_key_the_file_never_had(): void
    {
        $raw = $this->serverProperties();
        $out = (new PropertiesFormat)->apply($raw, ['simulation-distance' => '8']);

        $this->assertStringContainsString($raw, $out);
        $this->assertStringEndsWith('simulation-distance=8', $out);
    }

    public function test_properties_keeps_crlf_and_a_missing_final_newline(): void
    {
        $raw = "difficulty=easy\r\npvp=true\r\nmax-players=20";
        $out = (new PropertiesFormat)->apply($raw, ['pvp' => 'false']);

        $this->assertSame("difficulty=easy\r\npvp=false\r\nmax-players=20", $out);
    }

    public function test_properties_survives_an_emoji_that_needs_a_surrogate_pair(): void
    {
        $out = (new PropertiesFormat)->apply("motd=x\n", ['motd' => 'Hi 🎮']);

        // Past the basic plane, so it has to go out as a surrogate pair: one
        // five digit escape would be read back as four digits and a stray "4".
        $this->assertStringContainsString('Hi \\uD83C\\uDFAE', $out);
        $this->assertSame('Hi 🎮', (new PropertiesFormat)->parse($out)['motd']);
    }

    // -------------------------------------------------------------- palworld

    private function palworldSettings(): string
    {
        // One header, one comment the operator added, and the single tuple that
        // is the entire useful content of the file. The server name holds a
        // comma inside its quotes on purpose: a splitter that does not respect
        // quoting cuts the tuple in half right there.
        return implode("\n", [
            '; do not reformat this file, the game is fussy',
            '[/Script/Pal.PalGameWorldSettings]',
            'OptionSettings=(Difficulty=None,DayTimeSpeedRate=1.000000,NightTimeSpeedRate=1.000000,'
                .'ExpRate=1.000000,PalCaptureRate=1.000000,DeathPenalty=All,bIsPvP=False,'
                .'ServerPlayerMaxNum=32,ServerName="Bob\'s, Server",ServerDescription="",AdminPassword="",'
                .'PublicPort=8211,SomeKeyFromANewerBuild=Whatever)',
            '',
        ]);
    }

    public function test_palworld_parses_the_single_line_tuple(): void
    {
        $values = (new PalworldIniFormat)->parse($this->palworldSettings());

        $this->assertSame('1.000000', $values['DayTimeSpeedRate']);
        $this->assertSame('All', $values['DeathPenalty']);
        $this->assertSame('32', $values['ServerPlayerMaxNum']);
        // The comma lives inside the quotes and belongs to the value.
        $this->assertSame("Bob's, Server", $values['ServerName']);
        $this->assertSame('', $values['ServerDescription']);
        $this->assertSame('Whatever', $values['SomeKeyFromANewerBuild']);
        // The tuple itself is not offered as an editable 2 KiB blob.
        $this->assertArrayNotHasKey('/Script/Pal.PalGameWorldSettings.OptionSettings', $values);
    }

    public function test_palworld_changes_one_rate_and_nothing_else(): void
    {
        $raw = $this->palworldSettings();
        $out = (new PalworldIniFormat)->apply($raw, ['NightTimeSpeedRate' => '2']);

        // Only the tuple line, line 2, may differ.
        $this->assertOnlyTheseLinesChanged($raw, $out, [2]);

        $after = (new PalworldIniFormat)->parse($out);
        $this->assertSame('2', $after['NightTimeSpeedRate']);

        // Every other key comes back as the exact bytes it went in as, six
        // decimal places and all, and the unknown key is still there.
        $before = (new PalworldIniFormat)->parse($raw);
        unset($before['NightTimeSpeedRate'], $after['NightTimeSpeedRate']);
        $this->assertSame($before, $after);
        $this->assertStringContainsString('DayTimeSpeedRate=1.000000', $out);
        $this->assertStringContainsString('SomeKeyFromANewerBuild=Whatever', $out);
        $this->assertStringContainsString('; do not reformat this file, the game is fussy', $out);
    }

    public function test_palworld_keeps_quoting_and_refuses_to_break_the_tuple(): void
    {
        $out = (new PalworldIniFormat)->apply($this->palworldSettings(), [
            'ServerName' => 'A, "quoted" (name)',
            'ServerPlayerMaxNum' => '16',
        ]);

        // The four characters that would end the tuple early are stripped, the
        // quotes the key already had are kept, and the bare number stays bare.
        $this->assertStringContainsString('ServerName="A quoted name"', $out);
        $this->assertStringContainsString('ServerPlayerMaxNum=16', $out);
        $this->assertSame('A quoted name', (new PalworldIniFormat)->parse($out)['ServerName']);
    }

    public function test_palworld_appends_a_key_an_older_build_never_shipped(): void
    {
        $out = (new PalworldIniFormat)->apply($this->palworldSettings(), ['WorkSpeedRate' => '1.5']);

        $this->assertStringContainsString(',WorkSpeedRate=1.5)', $out);
        $this->assertSame('1.5', (new PalworldIniFormat)->parse($out)['WorkSpeedRate']);
    }

    public function test_palworld_refuses_a_file_with_no_tuple_rather_than_inventing_one(): void
    {
        $raw = "[/Script/Pal.PalGameWorldSettings]\n";
        $skipped = [];

        $out = (new PalworldIniFormat)->apply($raw, ['ExpRate' => '2'], $skipped);

        $this->assertSame($raw, $out);
        $this->assertSame(['ExpRate'], $skipped);
    }

    // ------------------------------------------------------------------- ini

    public function test_ini_addresses_by_section_and_leaves_comments_alone(): void
    {
        $raw = implode("\n", [
            '; global',
            'RootKey=1',
            '',
            '[Server]',
            '# how many',
            'MaxPlayers = 10',
            'Name=Test',
            '',
            '[Other]',
            'MaxPlayers=99',
            '',
        ]);

        $format = new IniFormat;
        $values = $format->parse($raw);

        $this->assertSame('1', $values['RootKey']);
        $this->assertSame('10', $values['Server.MaxPlayers']);
        $this->assertSame('99', $values['Other.MaxPlayers']);

        $out = $format->apply($raw, ['Server.MaxPlayers' => '24']);

        $this->assertOnlyTheseLinesChanged($raw, $out, [5]);
        // The spacing the line already had survives.
        $this->assertStringContainsString('MaxPlayers = 24', $out);
        $this->assertStringContainsString('[Other]', $out);
        $this->assertStringContainsString('MaxPlayers=99', $out);
    }

    public function test_ini_inserts_a_missing_key_into_its_own_section(): void
    {
        $raw = "[Server]\nName=Test\n\n[Other]\nX=1\n";
        $out = (new IniFormat)->apply($raw, ['Server.MaxPlayers' => '8']);

        $this->assertSame("[Server]\nName=Test\nMaxPlayers=8\n\n[Other]\nX=1\n", $out);
    }

    // ------------------------------------------------------------------ yaml

    private function bukkitYaml(): string
    {
        return implode("\n", [
            '# This is the main configuration file for Bukkit.',
            '# As you can see, there is quite a lot to configure!',
            'settings:',
            '  allow-end: true',
            '  warn-on-overload: true',
            '  shutdown-message: Server closed',
            'spawn-limits:',
            '  monsters: 70   # lower this first when the server lags',
            '  animals: 10',
            '  water-animals: 5',
            'ticks-per:',
            '  animal-spawns: 400',
            '  autosave: 6000',
            'aliases: now-in-commands.yml',
            '',
        ]);
    }

    public function test_yaml_addresses_by_dotted_path(): void
    {
        $values = (new YamlFormat)->parse($this->bukkitYaml());

        $this->assertSame('true', $values['settings.allow-end']);
        $this->assertSame('70', $values['spawn-limits.monsters']);
        $this->assertSame('6000', $values['ticks-per.autosave']);
        $this->assertSame('now-in-commands.yml', $values['aliases']);
        $this->assertSame('Server closed', $values['settings.shutdown-message']);
        // A mapping that holds other mappings is not itself a value.
        $this->assertArrayNotHasKey('spawn-limits', $values);
    }

    public function test_yaml_changes_one_key_and_keeps_every_comment(): void
    {
        $raw = $this->bukkitYaml();
        $out = (new YamlFormat)->apply($raw, ['spawn-limits.monsters' => '40']);

        $this->assertOnlyTheseLinesChanged($raw, $out, [7]);
        // The inline comment on the edited line survives too, because it is
        // the only documentation anybody has for that setting.
        $this->assertStringContainsString('monsters: 40   # lower this first when the server lags', $out);
        $this->assertStringContainsString('# This is the main configuration file for Bukkit.', $out);
    }

    public function test_yaml_inserts_under_an_existing_parent(): void
    {
        $out = (new YamlFormat)->apply($this->bukkitYaml(), ['spawn-limits.ambient' => '15']);

        $this->assertStringContainsString("  water-animals: 5\n  ambient: 15\n", $out);
        $this->assertSame('15', (new YamlFormat)->parse($out)['spawn-limits.ambient']);
    }

    public function test_yaml_says_so_rather_than_inventing_a_parent(): void
    {
        $skipped = [];
        $raw = $this->bukkitYaml();

        $out = (new YamlFormat)->apply($raw, ['nothing.like.this' => '1'], $skipped);

        $this->assertSame($raw, $out);
        $this->assertSame(['nothing.like.this'], $skipped);
    }

    public function test_yaml_quotes_a_value_that_would_otherwise_change_meaning(): void
    {
        $out = (new YamlFormat)->apply($this->bukkitYaml(), ['settings.shutdown-message' => 'no']);

        $this->assertStringContainsString("shutdown-message: 'no'", $out);
        $this->assertSame('no', (new YamlFormat)->parse($out)['settings.shutdown-message']);
    }

    // -------------------------------------------------------------- registry

    public function test_every_declared_format_has_a_parser(): void
    {
        foreach (array_keys(Formats::ALL) as $name) {
            $this->assertNotNull(Formats::make($name), $name.' has no parser');
        }

        $this->assertNull(Formats::make('not-a-format'));
    }
}
