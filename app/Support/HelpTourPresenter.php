<?php

namespace App\Support;

use App\Models\Help\HelpTour;
use App\Models\Help\HelpTourStep;

/**
 * How a tour goes out.
 *
 * Two shapes, deliberately different. The viewer gets one language — the
 * one they are reading the site in — and nothing about who it is for or
 * when it starts that the client does not need to know. The editor gets
 * every language side by side, because that is what it is editing.
 */
class HelpTourPresenter
{
    /**
     * For somebody being helped: flat wording in their own language.
     */
    public static function forViewer(HelpTour $tour): array
    {
        return [
            'id'            => $tour->id,
            'key'           => $tour->key,
            'mode'          => $tour->mode,
            'icon'          => $tour->icon,
            'color'         => $tour->color,
            'title'         => $tour->title,
            'description'   => $tour->description,
            'auto_start'    => $tour->auto_start,
            'show_launcher' => $tour->show_launcher,
            'steps'         => $tour->steps->map(fn (HelpTourStep $step) => [
                'id'        => $step->id,
                'target'    => $step->target,
                'placement' => $step->placement,
                'title'     => $step->title,
                'content'   => $step->content,
            ])->all(),
        ];
    }

    /**
     * For the editor: every locale, so a tour can be written in all of
     * them in one sitting.
     */
    public static function forEditor(HelpTour $tour): array
    {
        return [
            'id'            => $tour->id,
            'key'           => $tour->key,
            'route_pattern' => $tour->route_pattern,
            'mode'          => $tour->mode,
            'icon'          => $tour->icon,
            'color'         => $tour->color,
            'audience'      => $tour->audience,
            'roles'         => array_values(array_filter((array) $tour->roles)),
            'auto_start'    => $tour->auto_start,
            'show_launcher' => $tour->show_launcher,
            'is_enabled'    => $tour->is_enabled,
            'sort_order'    => $tour->sort_order,
            'translations'  => self::wordingOf($tour, ['title', 'description']),
            'steps'         => $tour->steps->map(fn (HelpTourStep $step) => [
                'id'           => $step->id,
                'target'       => $step->target,
                'placement'    => $step->placement,
                'sort_order'   => $step->sort_order,
                'translations' => self::wordingOf($step, ['title', 'content']),
            ])->all(),
        ];
    }

    /**
     * What a record says in every locale the site has, with the ones it
     * says nothing in present and empty so the editor has a field to fill.
     *
     * @param  HelpTour|HelpTourStep  $model
     * @param  array<int, string>  $fields
     */
    private static function wordingOf($model, array $fields): array
    {
        $wording = [];

        foreach (Locales::all() as $locale) {
            // No fallback: it would fill every locale with the English
            // text and the editor could not tell what has actually been
            // translated from what merely reads that way
            $translation = $model->translate($locale, false);

            $wording[$locale] = [];

            foreach ($fields as $field) {
                $wording[$locale][$field] = $translation?->{$field} ?? '';
            }
        }

        return $wording;
    }
}
