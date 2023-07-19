<?php

class User {

    // GENERAL

    public static function user_info($user_id) {
        $q = DB::query("SELECT user_id, first_name, phone, last_name, email, plot_id FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'user_id' => $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'plot_id' => $row['plot_id']
            ];
        } else {
            return [
                'user_id' => 0,
                'first_name' =>  '',
                'last_name' =>  '',
                'phone' => '',
                'email' =>  '',
                'plot_id' =>  ''
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());

        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_list($d = []){
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];

        if ($search){
            $where[] = "phone LIKE '%".$search."%' OR first_name LIKE '%".$search."%' OR email LIKE '%".$search."%'";
        }

        $where = $where ? "WHERE ".implode(" OR ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, first_name, last_name, email, phone, plot_id, last_login FROM users ".$where." LIMIT ".$offset.", ".$limit.";")
        or die (DB::error());

        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'user_id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'last_login' => $row['last_login'],
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = []) {
        $info = self::users_list($d);

        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_edit_window($d = []){
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []){
        $first_name = isset($d['first_name']) ? $d['first_name'] : '';
        $last_name = isset($d['last_name']) ? $d['last_name'] : '';
        $phone = isset($d['phone']) ? $d['phone'] : '';
        $email = isset($d['email']) ? trim($d['email']) : '';
        $plots = isset($d['plots']) ? $d['plots'] : null;
        $user_id = isset($d['user_id']) ? $d['user_id'] : null;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        $plot_nums = explode(', ', $plots);

        if(!$first_name && !$last_name && !$phone && !$email && !$plots) return "Fill in all fields of the field (except plots)";
        // update
        if ($user_id && is_numeric($user_id)) {
            $set = [];
            $set[] = "first_name='".$first_name."'";
            $set[] = "last_name='".$last_name."'";
            $set[] = "phone='".$phone."'";
            $set[] = "email='".$email."'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id=".$user_id." LIMIT 1;") or die (DB::error());

            foreach ($plot_nums as $num){
                DB::query("UPDATE user_plots SET plot_id=? WHERE user_id=? LIMIT 1;", [$num, $user_id]) or die (DB::error());
            }
        } else {
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                phone,
                email
            ) VALUES (
                '".$first_name."',
                '".$last_name."',
                '".$phone."',
                '".$email."'
            );") or die (DB::error());


            foreach ($plot_nums as $num){
                DB::query("INSERT INTO user_plots (user_id, plot_id) VALUES ($user_id, $num)");
            }
        }
        // output
        return User::users_fetch(['offset' => $offset]);
    }

}
