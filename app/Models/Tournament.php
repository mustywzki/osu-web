<?php

/**
 *    Copyright 2015-2017 ppy Pty. Ltd.
 *
 *    This file is part of osu!web. osu!web is distributed with the hope of
 *    attracting more community contributions to the core ecosystem of osu!.
 *
 *    osu!web is free software: you can redistribute it and/or modify
 *    it under the terms of the Affero GNU General Public License version 3
 *    as published by the Free Software Foundation.
 *
 *    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
 *    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *    See the GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace App\Models;

use Carbon\Carbon;

class Tournament extends Model
{
    protected $primaryKey = 'tournament_id';

    protected $dates = ['signup_open', 'signup_close', 'start_date', 'end_date'];

    public static function getGroupedListing()
    {
        $tournaments = self::query()
            ->orderBy('tournament_id', 'desc')
            ->get();

        $current = $tournaments->where('end_date', '>', Carbon::now());
        $previous = $tournaments->where('end_date', '<=', Carbon::now());

        return [
            'current' => $current,
            'previous' => $previous,
        ];
    }

    public function profileBanners()
    {
        return $this->hasMany(ProfileBanner::class, 'tournament_id');
    }

    public function registrations()
    {
        return $this->hasMany(TournamentRegistration::class, 'tournament_id');
    }

    public function isRegistrationOpen()
    {
        $now = Carbon::now();

        return $this->signup_open < $now && $this->signup_close > $now;
    }

    public function isTournamentRunning()
    {
        $now = Carbon::now();

        return $this->start_date < $now && $this->end_date > $now;
    }

    public function isStoreBannerAvailable()
    {
        return $this->tournament_banner_product_id !== null &&
            optional($this->end_date)->isFuture() ?? true;
    }

    public function isSignedUp($user)
    {
        if (!$user) {
            return false;
        }

        return $this->registrations()->where('user_id', '=', $user->user_id)->exists();
    }

    public function isValidRank($user)
    {
        if (!$user) {
            return false;
        }

        $stats = $user->statistics($this->playModeStr(), true)->firstOrNew([]);

        if ($this->rank_min !== null && $this->rank_min > $stats->rank_score_index) {
            return false;
        }

        if ($this->rank_max !== null && $this->rank_max < $stats->rank_score_index) {
            return false;
        }

        return true;
    }

    public function unregister($user)
    {
        //sanity check: we shouldn't be touching users once the tournament is already in action.
        if ($this->isTournamentRunning()) {
            return;
        }

        $this->registrations()->where('user_id', '=', $user->user_id)->delete();
    }

    public function register($user)
    {
        if ($this->isSignedUp($user)) {
            return;
        }

        //sanity check: we shouldn't be touching users once the tournament is already in action.
        if ($this->isTournamentRunning()) {
            return;
        }

        $reg = new TournamentRegistration();
        $reg->user()->associate($user);

        $this->registrations()->save($reg);
    }

    public function playModeStr()
    {
        return Beatmap::modeStr($this->play_mode);
    }

    public function pageLinks()
    {
        $links = [];

        if ($this->info_url !== null) {
            $links[] = [
                'url' => $this->info_url,
                'title' => trans('tournament.show.info_page'),
            ];
        }

        if ($this->isStoreBannerAvailable()) {
            $links[] = [
                'url' => route('store.products.show', $this->tournament_banner_product_id),
                'title' => trans('tournament.show.banner'),
            ];
        }

        return $links;
    }
}
