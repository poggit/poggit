if has("autocmd")
	augroup tmpl
		autocmd BufNewFile *.rs 0r .vim/template.rs
		autocmd BufNewFile *.ts 0r .vim/template.ts
		autocmd BufNewFile *.sass 0r .vim/template.sass
	augroup END
endif

command Build w | !cargo build

let g:sql_type_default = 'pgsql'
