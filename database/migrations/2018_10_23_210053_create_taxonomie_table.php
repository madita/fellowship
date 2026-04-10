<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class TaxonomiesTable.
 */
class CreateTaxonomieTable extends Migration
{
    /**
     * Table names.
     *
     * @var string The terms table name.
     * @var string The taxonomies table name.
     * @var string The pivot table name.
     */
    protected $terms;
    protected $taxonomies;
    protected $pivot;

    /**
     * Create a new migration instance.
     */
    public function __construct()
    {
        $this->terms = config('lecturize.taxonomies.terms.table', 'terms');
        $this->taxonomies = config('lecturize.taxonomies.taxonomies.table', 'taxonomies');
        $this->pivot = config('lecturize.taxonomies.pivot.table', 'taxables');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->terms, function (Blueprint $table) {
            $table->increments('id');

            $table->string('title')->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($this->taxonomies, function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->nullable();
            $table->integer('parent_id')->nullable()->unsigned()->index();

            $table->integer('term_id')
                ->nullable()
                ->unsigned()
                ->references('id')
                ->on($this->terms)
                ->onDelete('cascade');

            $table->string('taxonomy')->default('default');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->text('meta_desc')->nullable();
            $table->text('color')->nullable();

            $table->integer('parent')->unsigned()->default(0);

            $table->smallInteger('sort')->unsigned()->default(0);

            $table->boolean('visible')->default(1);
            $table->boolean('searchable')->default(1);
//            $table->json('properties')->nullable();
            $table->longText('properties')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')
                ->on($this->taxonomies)
                ->onDelete('cascade');

            $table->unique(['term_id', 'taxonomy']);
        });

        Schema::create($this->pivot, function (Blueprint $table) {
            // Make taxonomy_id NOT NULL since it's part of the primary key
            $table->integer('taxonomy_id')
                ->unsigned()
                ->references('id')
                ->on($this->taxonomies);

            $table->timestamps();

            // Use morphs() instead of nullableMorphs() to make the columns NOT NULL
            $table->morphs('taxable');

            // Now we can safely create the composite primary key
            $table->primary(['taxonomy_id', 'taxable_type', 'taxable_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->pivot);
        Schema::dropIfExists($this->taxonomies);
        Schema::dropIfExists($this->terms);
    }
}
