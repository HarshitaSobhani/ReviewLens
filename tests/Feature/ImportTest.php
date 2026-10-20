<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->product = Product::factory()->for($this->user)->create();
    $this->actingAs($this->user);
});

function csvFile(string $content, string $name = 'reviews.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

function upload($test, $product, string $content, string $name = 'reviews.csv')
{
    return $test->post("/products/{$product->id}/imports", ['file' => csvFile($content, $name)]);
}

$good = "review_text,stars,date,verified_purchase,source,id\n"
    ."\"Great kettle, boils fast and looks lovely\",5,2025-03-01,yes,Shop A,r1\n"
    ."\"Broke after two weeks, terrible quality\",1,2025-03-02,no,Shop A,r2\n"
    ."\"Great kettle, boils fast and looks lovely\",5,2025-03-01,yes,Shop A,r3\n"
    ."\"\",4,2025-03-05,yes,Shop A,r4\n"
    ."\"Okay value for the price, nothing special\",abc,2025-03-05,yes,Shop A,r5\n"
    ."\"Delivery was quick and packaging was fine\",4,not-a-date,yes,Shop A,r6\n";

it('previews with auto-detected headers and row-level feedback', function () use ($good) {
    upload($this, $this->product, $good)->assertRedirect();
    $import = ReviewImport::first();
    expect($import->mapping['body'])->toBe('review_text')->and($import->mapping['rating'])->toBe('stars')
        ->and(Review::count())->toBe(0); // nothing persisted before confirmation

    $this->get("/imports/{$import->id}")->assertInertia(fn ($p) => $p
        ->where('preview.total', 6)->where('preview.accepted', 2)->where('preview.duplicates', 1)->where('preview.rejected', 3)
        ->has('preview.errors', 3));
});

it('imports accepted rows, counts duplicates, and deletes the stored file', function () use ($good) {
    upload($this, $this->product, $good);
    $import = ReviewImport::first();
    $path = $import->stored_path;
    Storage::disk('local')->assertExists($path);

    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping])->assertRedirect();
    $import->refresh();

    expect($import->status)->toBe('completed')->and($import->accepted_rows)->toBe(2)
        ->and($import->duplicate_rows)->toBe(1)->and($import->rejected_rows)->toBe(3)
        ->and(Review::count())->toBe(2)->and($import->stored_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
    expect(Review::where('external_id', 'r1')->first())
        ->rating->toBe(5)->verified_purchase->toBeTrue()->source->toBe('Shop A');
});

it('detects duplicates against previously imported reviews and by external id', function () use ($good) {
    foreach ([1, 2] as $_) {
        upload($this, $this->product, $good);
        $import = ReviewImport::latest('id')->first();
        $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    }
    expect(Review::count())->toBe(2)->and(ReviewImport::latest('id')->first()->duplicate_rows)->toBe(3);
});

it('does not dedupe the same text across different products', function () use ($good) {
    $other = Product::factory()->for($this->user)->create();
    foreach ([$this->product, $other] as $p) {
        upload($this, $p, $good);
        $import = ReviewImport::latest('id')->first();
        $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    }
    expect(Review::count())->toBe(4);
});

it('rejects invalid uploads', function () {
    $this->post("/products/{$this->product->id}/imports", [])->assertSessionHasErrors('file');
    upload($this, $this->product, 'x', 'evil.php')->assertSessionHasErrors('file');
    upload($this, $this->product, '', 'empty.csv')->assertSessionHasErrors('file');
    upload($this, $this->product, "a,b\0c\n1,2\n")->assertSessionHasErrors('file');
    upload($this, $this->product, "col1,col1\nx,y\n")->assertSessionHasErrors('file');
    expect(ReviewImport::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('enforces the file size limit', function () {
    config(['reviewlens.import.max_file_kb' => 1]);
    upload($this, $this->product, "review\n".str_repeat("a long enough review text\n", 200))->assertSessionHasErrors('file');
});

it('enforces the row limit at preview and import', function () {
    config(['reviewlens.import.max_rows' => 3]);
    $csv = "review\n".implode("\n", array_map(fn ($i) => "Review number $i is fine", range(1, 5)))."\n";
    upload($this, $this->product, $csv);
    $import = ReviewImport::first();
    $this->get("/imports/{$import->id}")->assertInertia(fn ($p) => $p->where('previewError', fn ($e) => str_contains($e, 'more than 3')));
    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    expect($import->fresh()->status)->toBe('failed')->and(Review::count())->toBe(0);
});

it('requires a review text mapping and rejects unknown mapped columns', function () use ($good) {
    upload($this, $this->product, $good);
    $import = ReviewImport::first();
    $this->post("/imports/{$import->id}/confirm", ['mapping' => ['body' => null]])->assertSessionHasErrors('mapping');
    $this->post("/imports/{$import->id}/confirm", ['mapping' => ['body' => 'nope']])->assertSessionHasErrors('mapping');
    expect($import->fresh()->status)->toBe('previewed');
});

it('handles semicolon delimiters, BOM, quoted newlines and ragged rows', function () {
    $csv = "\xEF\xBB\xBFtext;rating\n\"Line one\nline two is great\";5\nshort row only text here ok\nToo;many;columns;here\n";
    upload($this, $this->product, $csv);
    $import = ReviewImport::first();
    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    $import->refresh();
    expect($import->accepted_rows)->toBe(2)->and($import->rejected_rows)->toBe(1);
    expect(Review::where('rating', 5)->first()->body)->toBe('Line one line two is great');
});

it('sanitises html in review text on import', function () {
    upload($this, $this->product, "review\n\"<script>alert(1)</script>Really nice <b>product</b> overall\"\n");
    $import = ReviewImport::first();
    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    expect(Review::first()->body)->not->toContain('<');
});

it('keeps imports private (IDOR)', function () use ($good) {
    upload($this, $this->product, $good);
    $import = ReviewImport::first();
    $this->actingAs(User::factory()->create());
    $this->get("/imports/{$import->id}")->assertNotFound();
    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping])->assertNotFound();
    $this->delete("/imports/{$import->id}")->assertNotFound();
});

it('removes reviews when a completed import is deleted and prunes abandoned uploads', function () use ($good) {
    upload($this, $this->product, $good);
    $import = ReviewImport::first();
    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    $this->delete("/imports/{$import->id}")->assertRedirect();
    expect(Review::count())->toBe(0);

    upload($this, $this->product, $good);
    ReviewImport::query()->update(['created_at' => now()->subDays(2)]);
    $this->artisan('reviewlens:prune-imports')->assertSuccessful();
    expect(ReviewImport::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('deletes analyses together with the reviews they cite when an import is removed', function () use ($good) {
    upload($this, $this->product, $good);
    $import = ReviewImport::first();
    $this->post("/imports/{$import->id}/confirm", ['mapping' => $import->mapping]);
    $run = new App\Models\AnalysisRun;
    $run->forceFill(['user_id' => $this->user->id, 'product_id' => $this->product->id, 'status' => 'pending', 'engine' => 'baseline', 'pipeline_version' => '1'])->save();
    app(App\Services\Analysis\AnalysisPipeline::class)->run($run);
    expect(App\Models\FindingEvidence::count())->toBeGreaterThan(0);

    $this->delete("/imports/{$import->id}");
    expect(App\Models\AnalysisRun::count())->toBe(0)->and(App\Models\FindingEvidence::count())->toBe(0);
});
