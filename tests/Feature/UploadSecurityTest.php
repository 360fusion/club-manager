<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Support\Csv;
use App\Support\ImageDownscaler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Decoding test photos needs more than the CLI default of 128M.
        ini_set('memory_limit', '512M');
        Storage::fake('public');
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
    }

    private function upload(UploadedFile $file, string $folder = 'documents')
    {
        return $this->actingAs($this->admin)->postJson(route('admin.media.store', ['clubSlug' => 'club-a']), ['folder' => $folder, 'file' => $file]);
    }

    public function test_the_media_library_rejects_svg_html_and_scripts(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onmouseover="alert(1)"></svg>');

        $this->upload($svg, 'logos')->assertStatus(422);
        $this->upload($svg, 'documents')->assertStatus(422);
        $this->upload(UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'))->assertStatus(422);
        $this->upload(UploadedFile::fake()->createWithContent('shell.php.png', '<?php echo 1;'))->assertStatus(422);
    }

    public function test_the_media_library_enforces_the_size_and_dimension_limits(): void
    {
        $this->upload(UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'))->assertStatus(422);
        $this->upload(UploadedFile::fake()->create('ok.pdf', 10240, 'application/pdf'))->assertOk();
        $this->upload(UploadedFile::fake()->image('huge.png', 6001, 10), 'images')->assertStatus(422);
        $this->upload(UploadedFile::fake()->image('fine.jpg', 3000, 2000), 'images')->assertOk();
    }

    public function test_photos_are_downscaled_and_the_dimensions_limit_applies_to_covers(): void
    {
        $file = UploadedFile::fake()->image('camera.jpg', 4000, 3000);
        ImageDownscaler::apply($file);
        [$width, $height] = getimagesize($file->getRealPath());

        $this->assertSame(1920, $width);
        $this->assertSame(1440, $height);

        $small = UploadedFile::fake()->image('small.jpg', 800, 600);
        ImageDownscaler::apply($small);
        $this->assertSame([800, 600], array_slice(getimagesize($small->getRealPath()), 0, 2));
    }

    public function test_post_attachments_and_block_images_only_accept_safe_types_and_sizes(): void
    {
        $base = ['title' => 'Post', 'slug' => 'post', 'status' => 'draft'];
        $url = route('admin.posts.store', ['clubSlug' => 'club-a']);

        $this->actingAs($this->admin)->post($url, $base + ['new_attachments' => [UploadedFile::fake()->createWithContent('x.html', '<script>1</script>')]])->assertSessionHasErrors('new_attachments.0');
        $this->actingAs($this->admin)->post($url, $base + ['new_attachments' => [UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')]])->assertSessionHasErrors('new_attachments.0');
        $this->actingAs($this->admin)->post($url, $base + ['block_files' => ['b1' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="1"/>')]])->assertSessionHasErrors('block_files.b1');
        $this->actingAs($this->admin)->post($url, $base + ['cover_image' => UploadedFile::fake()->create('cover.gif.html', 5, 'text/html')])->assertSessionHasErrors('cover_image');
    }

    public function test_avatars_reject_svg_and_oversize_files(): void
    {
        $this->actingAs($this->admin)->put(route('profile.update'), ['name' => 'A', 'email' => $this->admin->email, 'avatar' => UploadedFile::fake()->create('a.svg', 2, 'image/svg+xml')])->assertSessionHasErrors('avatar');
        $this->actingAs($this->admin)->put(route('profile.update'), ['name' => 'A', 'email' => $this->admin->email, 'avatar' => UploadedFile::fake()->image('a.jpg')->size(4097)])->assertSessionHasErrors('avatar');
    }

    public function test_csv_cells_that_look_like_formulas_are_neutralised_and_quoted(): void
    {
        $this->assertSame(["'=HYPERLINK(\"x\")", "'+SUM(1)", "'@a", '-5.00', 'Normal', ''], Csv::safe(['=HYPERLINK("x")', '+SUM(1)', '@a', '-5.00', 'Normal', '']));

        $line = Csv::line(['Bob "the" Builder', '=cmd|calc', 'a,b']);
        $this->assertSame("\"Bob \"\"the\"\" Builder\",'=cmd|calc,\"a,b\"\n", $line);
    }

    public function test_member_export_neutralises_formulas_in_names(): void
    {
        $user = User::factory()->create(['name' => '=HYPERLINK("http://evil","x")']);
        $this->club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        $csv = $this->actingAs($this->admin)->get(route('clubs.members.export', ['slug' => 'club-a']))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
    }
}
