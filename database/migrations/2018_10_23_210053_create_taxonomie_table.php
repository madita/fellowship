<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Class TaxonomiesTable
 */
class CreateTaxonomieTable extends Migration
{
    /**
     * Table names.
     *
     * @var string  $terms       The terms table name.
     * @var string  $taxonomies  The taxonomies table name.
     * @var string  $pivot       The pivot table name.
     */
    protected $terms;
    protected $taxonomies;
    protected $pivot;

    /**
     * Create a new migration instance.
     */
    public function __construct()
    {
        $this->terms      = config('lecturize.taxonomies.terms.table',      config('lecturize.taxonomies.terms_table',      'terms'));
        $this->taxonomies = config('lecturize.taxonomies.taxonomies.table', config('lecturize.taxonomies.taxonomies_table', 'taxonomies'));
        $this->pivot      = config('lecturize.taxonomies.pivot.table',      config('lecturize.taxonomies.pivot_table',      'taxables'));
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->terms, function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('title')->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();


            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($this->taxonomies, function(Blueprint $table)
        {
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

        Schema::create($this->pivot, function(Blueprint $table)
        {
            $table->integer('taxonomy_id')
                ->nullable()
                ->unsigned()
                ->references('id')
                ->on($this->taxonomies);

            $table->primary(['taxonomy_id', 'taxable_type', 'taxable_id']);

            $table->nullableMorphs('taxable');
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
