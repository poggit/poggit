table! {
    account (id) {
        id -> Varchar,
        name -> Nullable<Varchar>,
        acc_type -> Varchar,
        email -> Nullable<Varchar>,
        first_login -> Nullable<Timestamp>,
        last_login -> Nullable<Timestamp>,
    }
}

table! {
    login_history (token) {
        token -> Bpchar,
        account -> Nullable<Varchar>,
        ip -> Varchar,
        target -> Varchar,
        request_time -> Timestamp,
        success_time -> Timestamp,
    }
}

table! {
    repo (id) {
        id -> Varchar,
        owner -> Varchar,
        name -> Varchar,
        private -> Bool,
        fork -> Bool,
    }
}

joinable!(login_history -> account (account));
joinable!(repo -> account (owner));

allow_tables_to_appear_in_same_query!(
    account,
    login_history,
    repo,
);
