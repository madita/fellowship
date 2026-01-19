<?php

namespace App\Http\Controllers\Admin;

//use App\Helpers\WebhookHelper;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Event\Event;
use App\Models\Event\EventDetail;
use App\Models\Page;
use App\Models\User;
use App\Models\Wiki;
use App\Notifications\Announcement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
//use Lecturize\Taxonomies\Models\Term;
use Stevebauman\Purify\Facades\Purify;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Helpers\TaxonomyHelper;
use function PHPUnit\Framework\isEmpty;
use DateTime;

class MigrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function event(Request $request)
    {

        $links = [];
//        $this->events();
        $this->gallery();
        $this->linkGallery();


        dd('done event', $links);
    }

    public function wiki(Request $request)
    {

        $links = [];
        $this->wikiTerms();
        $this->wikiPages();
//        $this->wikiLinking();
//        $this->wikiTermsLinking();


        dd('done', $links);
    }


    //events
    protected function events()
    {
        $events = DB::connection('stadtwache')->select('select * from treffen_treffen order by entrydate');

        foreach ($events as $event) {


//            dd($event);
            $data= [];


            $eventItem = new Event();
            $eventItem->title = html_entity_decode($event->location);
            $eventItem->description = html_entity_decode($event->bericht);
            $eventItem->user_id = 1;
            $eventItem->event_type_id = 1;
            $eventItem->startDate = DateTime::createFromFormat('Ymd', $event->starttag);
            $eventItem->startTime = DateTime::createFromFormat('Hi', $event->startzeit);
            $eventItem->endDate = DateTime::createFromFormat('Ymd', $event->endtag);
            $eventItem->endTime = DateTime::createFromFormat('Hi', $event->endzeit);
            $eventItem->created_at = DateTime::createFromFormat('Ymd', $event->entrydate);


            $options = [
                'max' => $event->max_teiln,
                'creator' => $event->entryuser,
                'lastEditedBy' => $event->lasteditby,
                'albumName' => $event->foto_topic,
            ];

//            dd(json_encode($options));

            $eventItem->save();


            $eventItem->details()->create([
                'lat' => $event->x,
                'lng' => $event->y,
                'options' => json_encode($options),
            ]);

//


            }


//            if (request()->get('image')) {
//                $event->image = request()->get('image');
//
//                if (request()->get('cover_position')) {
//                    $event->cover_position = request()->get('cover_position');
//                }
//            }

//        }

//        dd($data);

    }

    //gallery

    protected function katOrdner($kat)
    {
        $out = $kat;
        $out = str_replace("ä", "ae", $out);
        $out = str_replace("Ä", "Ae", $out);
        $out = str_replace("ö", "oe", $out);
        $out = str_replace("Ö", "Oe", $out);
        $out = str_replace("ü", "ue", $out);
        $out = str_replace("Ü", "Ue", $out);
        $out = str_replace("é", "e", $out);
        $out = str_replace("è", "e", $out);
        $out = str_replace("ê", "e", $out);
        $out = str_replace("/", "_", $out);
        $out = str_replace("\\", "_", $out);
        $out = str_replace(":", "_", $out);
        $out = str_replace("?", "_", $out);
        $out = str_replace("&", "_", $out);
        $out = str_replace("(", "_", $out);
        $out = str_replace(")", "_", $out);
        $out = str_replace("&szlig;", "ss", htmlentities($out));
        return $out;
    }

    protected function gallery()
    {



//        $galleries = DB::connection('stadtwache')->select('select * from treffen_bilder');
//        $collections = DB::connection('stadtwache')->select('SELECT DISTINCT(topic)  FROM treffen_bilder ORDER BY datum DESC');
        $collections = DB::connection('stadtwache')
            ->select('SELECT COUNT(id) AS count, topic, MIN(datum) AS datum FROM treffen_bilder GROUP BY topic;');
//        dd($collections);

//        dd($galleries);

        foreach ($collections as $collection) {
            $collectionItem = Collection::create([
                'user_id' => 1,
                'taxonomy_id' => 1,
                'name' => $collection->topic,
                'crated_at' => $collection->datum
            ]);


            $galleries = DB::connection('stadtwache')->select('select * from treffen_bilder where topic = "' . $collection->topic . '"');


            if (count($galleries) > 0) {
//                $data['images'][$collection->id] = $collection->topic;



//                    dd($galleries);
                $galleryPath = "C:\Users\madit\PhpstormProjects\stadtwache\\treffen\\uploads";

                foreach ($galleries as $gallery) {
//                    if($gallery->f)

                    try{

                        //@unlink("uploads/".katOrdner($entry["topic"])."/$id.jpg");
//                $altText = trim(preg_replace('/Ikonographie\s?=\s?/', "", $line));
                        $fileName=$this->katOrdner($gallery->topic). "\\".$gallery->id.".jpg";
                        $pathToFile = $galleryPath . "\\" . $fileName;
                        $pathToFileTemp = $galleryPath . "\copy\\" . $fileName;
                        copy($pathToFile, $pathToFileTemp);


                        $topicSlug = Str::slug($gallery->topic, '-');
                        $date = Str::of($gallery->datum)->substr(0, 10);

                        $useFileName = "event-{$topicSlug}-{$date}.jpg";

                        $image = $collectionItem->addMedia($pathToFileTemp)
                            ->usingFileName($useFileName)
                            ->withCustomProperties(['album' => $gallery->topic, 'uploader' => $gallery->uploader])
                            ->toMediaCollection("gallery");


//                        $media = $collection->addMedia($request->file('file'))->toMediaCollection('gallery');

                        // Set the caption as a custom property if provided
                        if ($gallery->info) {
                            $image->setCustomProperty('caption', $gallery->info);
                        }
                        $image->created_at = DateTime::createFromFormat('Y-m-d H:i:s', $gallery->datum);
                        $image->save();

                    } catch (Exception $e) {
//                        echo $e."<br>";
                        echo $pathToFile."<br>";
                        continue;
                    }


                }

            } else {
                $event = null;
                $data['noimages'][$collection->id] = $event->foto_topic;
            }


        }

//        dd($events);

    }


    protected function linkGallery()
    {
        $events = Event::all();

        $notfound = [];


        foreach ($events as $event) {

            $details = EventDetail::where('event_id', $event->id)->first();

//           $details =  $event->details();
            if(isset($details)) {
//                dd($details);
                $options = json_decode($details['options']);

                $gallery = Collection::where('name', $options->albumName)->first();
                if($gallery) {
//                    dd($gallery);
//                    $gallery
                    $event->relate($gallery);
                } else {
                    $notfound[] = ['event' => $event->id, 'albumName'=>$options->albumName];
                }

//                dd($options);


            }

        }

//        dd($notfound);

    }


    //wiki

    protected function wikiTerms()
    {
        // CREATE wiki terms
        $categories = DB::connection('stadtwache')->select('select * from ww_category order by cat_subcats desc');

//        dd($categories);
        //\[\[(.*?)(\|(.*?))?\]\]

        foreach ($categories as $category) {

            $url = "https://stadtwache.net/wachewiki/api.php?action=query&cmtitle=Kategorie:{$category->cat_title}&list=categorymembers&prop=revisions&titles=Kategorie:{$category->cat_title}&rvslots=*&rvprop=content&formatversion=2&rvlimit=1&cmlimit=1000&format=json";

            $responserev = Http::get($url);
            $catresponserev = $responserev->json();


            $desc = '';
            if (isset($catresponserev['query']['pages'][0]['revisions'])) {
                $desc = $catresponserev['query']['pages'][0]['revisions'][0]['slots']['main']['content'];
            }

            $url = "https://stadtwache.net/wachewiki/api.php?action=query&list=categorymembers&cmtitle=Kategorie:{$category->cat_title}&cmtype=subcat&cmlimit=1000&format=json";

            $response = Http::get($url);
            $catresponse = $response->json();

//            dd($category->cat_title, $catresponse['query']);
            $members = $catresponse['query']['categorymembers'];

            //             $desc = '';
//             if (isset($catresponse['query']['pages'][0]['revisions'])) {
//                 $desc = $catresponse['query']['pages'][0]['revisions'][0]['slots']['main']['content'];
//             }

            $categoryname = Str::replace("Kategorie:", "", $category->cat_title);
            $tag = Str::replace("_", " ", $categoryname);
            $term = Term::firstOrCreate(['title' => trim(Str::replace("_", " ", $tag))]);


            if ($category->cat_subcats > 0) {
//            if ($category->cat_subcats == count($members)) {

                //                $tag = Str::replace("_"," ",$category)


                $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
                $taxonomy->description = $desc;
                $taxonomy->save();


                foreach ($members as $member) {
                    if (Str::contains($member['title'], "Kategorie:")) {
                        $category = Str::replace("Kategorie:", "", $member['title']);
                        $tag = Str::replace("_", " ", $category);
                        TaxonomyHelper::createTaxables(trim($tag), 'wiki', $taxonomy->id);
                        //                        $childtaxonomy              = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id, 'parent_id' => $taxonomy->id]);
                        //                        $childtaxonomy->description = $desc;
                        //                        $childtaxonomy->save();
                    }
                }

            } else {

//                dd($category, $term, $members);
                //                TaxonomyHelper::createTaxables(trim($tag), 'wiki');
                $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
                $taxonomy->description = $desc;
                $taxonomy->save();
                //                TaxonomyHelper::createTaxables(Str::replace("Kategorie:", "", $category->cat_title), 'wiki');
            }

        }
    }

    protected function wikiPages()
    {
        //CREATE PAGES
        //        $wpages = DB::connection('stadtwache')->select('select distinct(page_title) from ww_page WHERE page_is_redirect = 0');
        $wpages = DB::connection('stadtwache')->select('select distinct(page_title),page_is_redirect  from ww_page');


        $archivePath = "C:\Users\madit\OneDrive\Pictures\Wache\website\wachewiki";

        foreach ($wpages as $wpage) {

            $status = null;
            if ($wpage->page_is_redirect === 1) {
                $status = 'redirect';
            }

            try {
                $url =
                    "https://stadtwache.net/wachewiki/api.php?action=query&prop=revisions&titles={$wpage->page_title}&rvslots=*&rvprop=content|timestamp&formatversion=2&rvlimit=max&format=json&rvdir=newer";

                $response = Http::get($url);
                //dd($response->json());

            } catch (Exception $e) {
                dd($e, $wpage);
            }

            $pageresponse = $response->json();
            $page_title = Str::replace("Portal:", "", $pageresponse['query']['pages'][0]['title']);
            //            if($pageresponse['query']['pages'][0]['missing'] === true) {
            //                continue;
            //            }

            if (!isset($pageresponse['query']['pages'][0]['revisions'])) {
                continue;
            }
            $page_rev = $pageresponse['query']['pages'][0]['revisions'];
            //            $page = auth()->user()->pages()->create($request->only($this->getUpdatableColumns()));

            $page = Page::create([
                'title' => $page_title,
                'user_id' => 1,
                'content' => $pageresponse['query']['pages'][0]['revisions'][0]['slots']['main']['content'],
                'created_at' => $pageresponse['query']['pages'][0]['revisions'][0]['timestamp'],
                'updated_at' => $pageresponse['query']['pages'][0]['revisions'][0]['timestamp']]);
            //            dd($pageresponse['query']['pages'][0]['revisions']);
            $revisions = $pageresponse['query']['pages'][0]['revisions'];


            $wiki = new Wiki(['title' => $page_title, 'slug' => $page->slug, 'status' => $status]);

            $page->wikiable()->save($wiki);
            //            $page->save();

            foreach ($revisions as $revision) {

                //                        $content = $revision['slots']['main']['content'];
                //                dd($content)

                $page->update([
                    'content' => $revision['slots']['main']['content'],
                    'updated_at' => $revision['timestamp']]);

                //dd($revision);
            }


            $last_content = $pageresponse['query']['pages'][0]['revisions'][count($revisions) - 1]['slots']['main']['content'];

            $last_content = preg_replace('/=====(.*?)=====/', '<h5 id="$1">$1</h5>', $last_content);
            $last_content = preg_replace('/====(.*?)====/', '<h4 id="$1">$1</h4>', $last_content);
            $last_content = preg_replace('/===(.*?)===/', '<h3 id="$1">$1</h3>', $last_content);
            $last_content = preg_replace('/==(.*?)==/', '<h2 id="$1">$1</h2>', $last_content);


            $last_content = preg_replace('/{{dts\|(\d+)\|(\d+)\|(\d+)}}/', '$1.$2.$3', $last_content);

            $last_content = Str::replace("‎", "", $last_content);

            //$last_content = preg_replace('/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/', '#$1', $last_content);
            //$last_content = preg_replace('/\[\[Portal:(.*?)(\|(.*?))?\]\]/', '#$1', $last_content);

            //lists start
            preg_match_all(
                "/(\s*\*(.*)\n)+/", $last_content, $matches
            );

            //                    dd($matches, $page);

            foreach ($matches[0] as $key => $item) {
                $list = "<ul>";
                $sublist = false;
                foreach (preg_split("/((\r?\n)|(\r\n?))/", $item) as $key => $line) {
                    if (Str::startsWith(trim($line), "**")) {
                        if (!$sublist) {
                            $sublist = true;
                            $list .= "<ul>";
                        }
                        $line = trim(Str::replace("**", "", $line));
                        $list .= "<li>{$line}</li>";
                    } else {
                        if ($sublist) {
                            $sublist = false;
                            $list .= "</ul>";
                        }
                        if (Str::startsWith(trim($line), "*")) {
                            $line = trim(Str::replace("*", "", $line));
                            $list .= "<li>{$line}</li>";
                        }
                    }
                }
                $list .= "</ul>";
                $last_content = Str::replace($item, $list, $last_content);
            }
            //lists end


            //            preg_match_all(
            //                "/{\|\s*.*\s*(\|(.*?)\n)*\s*(!.*\n)*(\s*\|.*)+/", $last_content, $matches
            //            );
            preg_match_all(
                "/{\|(.*\n)+?\|}/", $last_content, $matches
            );

            //{\|\s*.*\s*(\|(.*?)\n)*(!.*\n)*(\|(.*?)\n)+\s*\|}
            //{\|([^.]*?)\|}
            //if($page->title == 'Abteilungsleiter')
            //                    dd($matches, $page);
            foreach ($matches[0] as $key => $item) {
                $temp = $item;
                preg_match_all('/\s*\|\s*(.*?)\|\n/', $item, $attributes);
                //                        dd($attributes);

                $lines = preg_split("/\|-/", $item);
                if (Str::contains($item, "||")) {
                    $type = "table";

                    $table = "<div class=\"tableWrapper\"><table class=\"wikitable\">";

                    foreach ($lines as $index => $line) {
                        $tableline = "";

                        $t = Str::contains($line, "!") ? "th" : "td";
                        //ignore first class line
                        if ($index === 0 && $t === "td") continue;
                        //correct last line
                        if ($index === count($lines) - 1 && Str::contains($line, "|}"))
                            $line = Str::replace("|}", "", $line);

                        $line = preg_replace("/\|/", "", $line, 1);

                        $tlines = preg_split("/(\|\|)|(!!)/", $line);

//                        if($index===1) {
//
//                        }


                        foreach ($tlines as $i => $tl) {
                            $titem = [];
                            //                                    $titem = explode("|", $tl, 2);
                            $titem = preg_split("/(\|)|(!)/", $tl, 2);

                            $class = preg_replace("/\s|\n/", "", $titem[0]);
                            if (count($titem) > 1 && $class !== "") {
                                $tableline .= "<{$t} {$class}>" . trim(preg_replace("/\t|\n/", "", $titem[1])) . "</{$t}>";
                            } else {
                                $tableline .= "<{$t}>" . trim(preg_replace("/\t|\n/", "", $tl)) . "</{$t}>";
                            }
                        }
                        $table .= "<tr>{$tableline}</tr>";

                    }
                    $table .= "</tbody></table></div>";

                    $last_content = Str::replace($temp, $table, $last_content);
                } else {
                    //was div
                    $type = "table";

//                    $itemtemp = Str::replace("<div","<p",$item);
//                    $itemtemp = Str::replace("</div>","</p>",$itemtemp);

                    $itemtemp = Str::replace("|}", "", $item);

//                    $itemtemp = preg_replace("/\|(.*)\n\|.*\|/","-SPLIT-",$item);

                    if (Str::contains($item, "100%")) {
                        $itemtemp = preg_replace("/\|(.*)\n\|.*\|/", "", $itemtemp);
                        $itemtemp = Str::replace("{", "", $itemtemp);
                        $last_content = Str::replace($item, $itemtemp, $last_content);
                    } elseif (Str::contains($item, "50%")) {
//                        dd($item);
//                        $itemtemp = Str::replace("|}","",$itemtemp);
//                        $itemtemp = Str::replace("{|","",$itemtemp);
//                        $itemtemp = Str::replace("|-","",$itemtemp);
                        $itemtemp = preg_replace("/{\|\n\|-\n/", "", $itemtemp);

                        $titem = preg_split("/\| width(.*)\|/", $itemtemp);

                        $table = "<table><tr>";
                        foreach ($titem as $td) {
                            if ($td !== "") {
                                $table .= "<td>{$td}</td>";
                            }
                        }

                        $table .= "</tr></table>";
//                        dd($table);

                        $last_content = Str::replace($item, $table, $last_content);

                        // $table .= "<tr>{$tableline}</tr>";
                    } else {
//                        dd('else',$item);

                    }

                    /****Nooooope
                     * foreach ($matches[0] as $att) {
                     *
                     * $item = $att;
                     *
                     * preg_match_all('/\|\s*(.*?)\s*\|\n/', $item, $attributes);
                     *
                     * foreach ($attributes[0] as $index => $atr) {
                     * $replace = $attributes[1][$index];
                     * //                            dd($atr, $replace);
                     *
                     * if(Str::contains($attributes[1][$index], "50%")) {
                     * $replace = "class=\"col-6\" {$attributes[1][$index]}";
                     * }
                     * //                            $replace = "<div {$replace}>";
                     * $replace = "<table>";
                     * //                            if ($index === 0) {
                     * //                                $item = Str::replaceFirst($atr, $replace, $item);
                     * $item = "<td>{$atr}</td>";
                     * //                            } else {
                     * //                                $replace = "</div>{$replace}";
                     * //                                $item = Str::replaceFirst($atr, $replace, $item);
                     * //                                //                                $item = preg_replace('/\|\s*(.*?)\s*\|\n/', '</div><div $1>', $item);
                     * //                            }
                     * //                            dd($item);
                     *
                     * //                            $item = Str::replace("|}", "</div>", $item);
                     * $item = Str::replace("|}", "</table>", $item);
                     * //                            $item = preg_replace('/{\|\s*(.*?)\n/', '', $item);
                     *
                     * $item = Str::replace("{|", "", $item);
                     * $item = Str::replace("|-", "", $item);
                     * $item = "<table class=\"row\">{$item}</table>";
                     * }
                     *
                     * $last_content = Str::replace($att, $item,  $last_content);
                     * } */////
                    //                    dd($att, $item);
                    //                                                $item = Str::replace("|}", "</div>", $item);
                    //                                                $item = preg_replace(['/{\|/', '/\|}/'], '', $item, 1);

                    //                    $last_content = Str::replace($att, $atr, $last_content);

                    //                    dd($last_content);
                }

            }


            if (Str::contains($last_content, "{{Infobox")) {
                preg_match_all(
                    "/{{Infobox\s(.*)\n((.*)\n)+}}/", $last_content, $matches
                );

                foreach ($matches[0] as $key => $item) {
                    $temp = $item;
                    $item = preg_replace('/{{Infobox ' . $matches[1][$key] . '\n/', '', $item);
                    $item = Str::replace("}}", "", $item);
                    $lines = explode("|", $item);

                    $table = "<table class=\"infobox\" data-infobox=\"{$matches[1][$key]}\">";
                    foreach ($lines as $line) {
                        if ($line === "") {
                            continue;
                        }

                        if (Str::startsWith($line, "Name")) {
                            $title = preg_replace('/Name\s?=\s?/', "", $line);
                            $table .= "<tr><th colspan=\"2\">{$title}</th></tr>";
                        } elseif (Str::startsWith($line, "Ikonographie")) {
                            $altText = trim(preg_replace('/Ikonographie\s?=\s?/', "", $line));
                            $pathToFile = $archivePath . "\\" . Str::replace(" ", "_", $altText);
                            $pathToFileTemp = $archivePath . "\archive\\" . Str::replace(" ", "_", $altText);
                            copy($pathToFileTemp, $pathToFile);
                            $image = $page->addMedia($pathToFile)->toMediaCollection('images');
                            //
                            $replace = "<img src=\"{$image->getUrl()}\" alt=\"{$altText}\">";
                            //
                            $table .= "<tr><th colspan=\"2\">{$replace}</th></tr>";
                        } else {
                            $line = preg_replace('/\s?=\s?/', '</td><td>', $line);
                            $table .= "<tr><td>{$line}</td></tr>";
                        }

                    }
                    $table .= "</table>";
                    //                            $last_content = preg_replace('/{{Infobox\s(.*)\n((.*)\n)+}}/',$table, $last_content);
                    $last_content = Str::replace($temp, $table, $last_content);
                }

            }

            preg_match_all(
                "/\[\[:Kategorie:(.*?)(\|(.*?))?\]\]/", $last_content, $matches
            );

            foreach ($matches[0] as $key => $item) {
                //                    $term = Term::where('title', $matches[1][$key])->first();


                //                if($matches[1][$key]=='Ermittlung_und_Kriminalistik') {
                //                    dd($matches);
                //                }
                $term = Term::where('slug', Str::slug($matches[1][$key]))->first();
                if ($term === null) {
                    $tag = Str::replace("_", " ", $matches[1][$key]);
                    $term = Term::firstOrCreate(['title' => trim($tag)]);
                }


                $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $term->title;
                $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;
                $replace =
                    "<a style=\"font-weight:600;color:null\" data-term-id=\"{$term->id}\" data-tag=\"{$term->title}\" data-linked-resource-type=\"terms\" data-alternative=\"{$alternative}\" href=\"/wiki/category/{$term->slug}\" contenteditable=\"false\">#{$title}</a>";
                $last_content = Str::replace($item, $replace, $last_content);
            }

            //            preg_match_all(
            //                "/\[\[Portal:(.*?)\|(.*?)\]\]/", $last_content, $matches
            //            );
            //            foreach ($matches[0] as $key => $item) {
            //                $tag = Str::slug(Str::replace("Portal:", "", $matches[1][$key]));
            //                $term = Term::where('slug', $tag)->first();
            //                if($term === null) {
            //                    $term = Term::firstOrCreate(['title' => trim($tag)]);
            //                }
            //
            //                $replace      =
            //                    "<a style=\"font-weight:600;color:null\" term-id=\"{$term->id}\" data-tag=\"{$term->title}\" data-linked-resource-type=\"terms\" alternative=\"{$matches[2][$key]}\" href=\"/tag/{$term->slug}\" contenteditable=\"false\">#{$matches[2][$key]}</a>";
            //                $last_content = Str::replace($item, $replace, $last_content);
            //            }

            preg_match_all(
                "/\[\[Image:(.*?)(\|.*)?\]\]/", $last_content, $matches
            );

            foreach ($matches[0] as $key => $item) {
                $pathToFile = $archivePath . "\\" . Str::replace(" ", "_", $matches[1][$key]);
                $pathToFileTemp = $archivePath . "\archive\\" . Str::replace(" ", "_", $matches[1][$key]);
                copy($pathToFileTemp, $pathToFile);
                $image = $page->addMedia($pathToFile)->toMediaCollection('images');
                $alt = explode("|", $item);
                $altText = Str::replace("'", "", $alt[count($alt) - 1]);
                $replace = "<img src=\"{$image->getUrl()}\" alt=\"{$altText}\">";
                $last_content = Str::replace($item, $replace, $last_content);
            }

            preg_match_all(
                "/\[\[Bild:(.*?)(\|.*)?\]\]/", $last_content, $matches
            );
            foreach ($matches[0] as $key => $item) {
                $pathToFile = $archivePath . "\\" . Str::replace(" ", "_", $matches[1][$key]);
                $pathToFileTemp = $archivePath . "\archive\\" . Str::replace(" ", "_", $matches[1][$key]);
                copy($pathToFileTemp, $pathToFile);
                $image = $page->addMedia($pathToFile)->toMediaCollection('images');
                $alt = explode("|", $item);
                //                    dd($image->getUrl());
                $altText = Str::replace("'", "", $alt[count($alt) - 1]);
                $replace = "<img src=\"{$image->getUrl()}\" alt=\"{$altText}\">";
                $last_content = Str::replace($item, $replace, $last_content);
            }

            $last_content = preg_replace('/\'\'\'(.*?)\'\'\'/', '<strong class="highlight">$1</strong>', $last_content);
            $last_content = preg_replace('/\'\'(.*?)\'\'/', '<em class="highlight">$1</em>', $last_content);

            preg_match_all(
                "/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/", $last_content, $matches
            );

            //            if($page->title == "Ermittlung") {
            //                dd($matches);
            //            }

            $last_content = preg_replace('/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/', '', $last_content);
            $last_content = preg_replace('/\[\[de:(.*?)(\|(.*?))?\]\]/', '', $last_content);
            $last_content = preg_replace('/\[\[en:(.*?)(\|(.*?))?\]\]/', '', $last_content);

            $terms = [];
            $test = $terms;

            foreach ($matches[1] as $item) {
                $item = preg_replace('/[^\x20-\x7E]/', '', $item);
                array_push($terms, strip_tags($item));
            }


            preg_match_all(
                "/{{(.*?)}}/", $last_content, $matches
            );

            foreach ($matches[1] as $item) {
                $item = preg_replace('/[^\x20-\x7E]/', '', $item);
                array_push($terms, strip_tags($item));
            }

            $terms = array_values(array_unique($terms));

            //            $terms = array_unique($terms);
            //            if($page->slug == 'herr-clete-musikergilde') {
            ////                dd('test',array_merge($test, $matches[1]));
            //                dd($terms,$test,$matches[1], $page);
            //            }

            //            if($page->title=='Bibliothekar') {
            //                dd($terms,$test, $page->hasCategory(trim($term), 'wiki'));
            //            }

            foreach ($terms as $term) {
                $term = Str::replace("_", " ", $term);
                if (!$page->hasCategory(trim($term), 'wiki')) {
                    $page->addCategory(trim($term), 'wiki');
                }
            }


            $last_content = preg_replace('/{{(.*?)}}/', '', $last_content);


            //            if($page->title == "DOG")
            //            dd($page->title, $last_content);
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $last_content) as $key => $line) {
                $paragraph = "<p>{$line}</p>";
                //                $test[] = $line;
                //                $test2[] = $paragraph;
                if (trim($line) != "" && $line != " " && $paragraph != "<p></p>" && $paragraph != "<p> </p>" && !preg_match('/<\/h\d>/', $line)) {
                    $last_content = Str::replace($line, $paragraph, $last_content);
                }
            }

            $links = [];
            preg_match_all(
                "/\[(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})\s(.*?)\]/", $last_content, $matches
            );
            //                                    dd($slug,$matches);

            if (count($matches[0]) > 0) {

                foreach ($matches[1] as $key => $link) {
                    $links[$page->title][] = $link . " - " . $matches[2][$key];
                    if (Str::contains($link, "stadtwache.net")) {
                        $wache[$page->title][] = $link . " - " . $matches[2][$key];
                    }

                    $link = "<a href=\"{$link}\">{$matches[2][$key]}</a>";


                    $last_content = Str::replace($matches[0][$key], $link, $last_content);
                }
            }

            $last_content = preg_replace('/<discwiki>(.*?)<\/discwiki>/', '<a data-linked-resource-type="discwiki" href="https://www.thediscworld.de/index.php/$1">$1</a>', $last_content);


            preg_match_all(
                "/<single>(.*?)<\/single>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
//                dd($matches);
//                foreach ($matches[1] as $key => $number) {
//                    $link ="getlink";
//                    $title = "gettitle";
//                    $link = "<a href=\"{$link}\">{$title}</a>";
//
//                    $last_content = Str::replace($matches[0][$key], $link, $last_content);
//                }
            }

            preg_match_all(
                "/<coop>(.*?)<\/coop>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
//                dd($matches);
            }

            preg_match_all(
                "/<multi>(.*?)<\/multi>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
//                dd($matches);
            }

            preg_match_all(
                "/<live>(.*?)<\/live>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
//                dd($matches);
            }

            preg_match_all(
                "/<puescho>(.*?)<\/puescho>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
                //                dd($matches);
            }

            preg_match_all(
                "/<bgesp>(.*?)<\/bgesp>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
                //                dd($matches);
            }

            preg_match_all(
                "/<char>(.*?)<\/char>/", $last_content, $matches
            );
            if (count($matches[0]) > 0) {
//                dd($matches);
            }


            $page->update([
                'content' => $last_content]);


            $last_content = Purify::clean($last_content);
            //            dd($last_content);
            //Portal:|Kategorie:([\w'-äÄÜüÖö]+)\|
            //    \[\[Kategorie:(.*?)\|(.*?)\]\]


        }
    }

    protected function wikiLinking()
    {
        $links = [];
        $wikis = Wiki::all();

        foreach ($wikis as $wiki) {
//            dd($wiki);
            $model = $wiki->wikiable_type;
            $data = Page::where('slug', $wiki->slug)->first();
            //            $data  = $model::where('id', $wiki->wikiable_id)->first();
            //            if($data->slug==='frog') {
            $content = $data->content;
//


            preg_match_all(
                "/\[\[(.*?)(\|(.*?))?\]\]/", $content, $matches
            );
            foreach ($matches[0] as $key => $item) {
                //get anker
                $title = explode("#", $matches[1][$key]);
                $page = Page::where('title', $title[0])->first();
                if ($page) {

                    $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $page->title;
                    $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;


                    $replace =
                        "<a wiki-id=\"{$page->id}\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$title}</a>";
                    $content = Str::replace($item, $replace, $content);

                }
            }


            preg_match_all(
                "/\[(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})\s(.*?)\]/", $content, $matches
            );
            //                                    dd($slug,$matches);

            if (count($matches[0]) > 0) {

                foreach ($matches[1] as $key => $link) {
                    $links[$data->title][] = $link . " - " . $matches[2][$key];
                    if (Str::contains($link, "stadtwache.net")) {
                        $wache[$data->title][] = $link . " - " . $matches[2][$key];
                    }

                    $link = "<a href=\"{$link}\">{$matches[2][$key]}</a>";


                    $content = Str::replace($matches[0][$key], $link, $content);
                }
            }


            $data->update([
                'content' => $content,]);


//            return $links;


            //                dd($content);


            //} //end frog

        }

//        dd($links, $wache);
    }

    protected function wikiTermsLinking()
    {

        $taxonomies = Taxonomy::where('taxonomy', 'wiki')->where('description', 'LIKE', '%[[%')->get();

        foreach ($taxonomies as $taxonomy) {

            $description = $taxonomy->description;

            preg_match_all(
                "/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/", $description, $matches
            );


            foreach ($matches[0] as $key => $item) {

                $term = Term::where('slug', Str::slug($matches[1][$key]))->first();
                if ($term === null) {
                    $tag = Str::replace("_", " ", $matches[1][$key]);
                    $term = Term::firstOrCreate(['title' => trim($tag)]);
                }

                $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $term->title;
                $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;
                $replace =
                    "<a style=\"font-weight:600;color:null\" term-id=\"{$term->id}\" data-tag=\"{$term->title}\" data-linked-resource-type=\"terms\" alternative=\"{$alternative}\" href=\"/wiki/category/{$term->slug}\" contenteditable=\"false\">#{$title}</a>";
                $description = Str::replace($item, $replace, $description);
            }


            preg_match_all(
                "/\[\[(.*?)(\|(.*?))?\]\]/", $description, $matches
            );
//            dd($matches);
            foreach ($matches[0] as $key => $item) {
                //get anker
                $title = explode("#", $matches[1][$key]);
                $page = Page::where('title', $title[0])->first();
                if ($page) {

                    $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $page->title;
                    $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;


                    $replace =
                        "<a wiki-id=\"{$page->id}\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$title}</a>";
                    $description = Str::replace($item, $replace, $description);

                }
            }


            $taxonomy->description = $description;
            $taxonomy->update();
        }


    }
}
