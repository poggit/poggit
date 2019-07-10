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
    artifact (id) {
        id -> Int4,
        mime -> Varchar,
        default_name -> Varchar,
        source -> Varchar,
        downloads -> Int4,
        created -> Timestamp,
        expiry -> Nullable<Timestamp>,
        data -> Bytea,
        dep_repo -> Nullable<Varchar>,
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
    project (id) {
        id -> Int4,
        owner -> Varchar,
        repo -> Varchar,
        name -> Varchar,
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

joinable!(artifact -> repo (dep_repo));
joinable!(login_history -> account (account));
joinable!(project -> account (owner));
joinable!(project -> repo (repo));
joinable!(repo -> account (owner));

allow_tables_to_appear_in_same_query!(
    account,
    artifact,
    login_history,
    project,
    repo,
);
