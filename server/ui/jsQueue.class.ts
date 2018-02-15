import {minimizable_ext, ResFile} from "../res/ResFile.class"
import {SECRETS} from "../secrets"

export class jsQueue{
	files: ResFile[] = []

	add(dir: string, internal: string, name: string, ext: minimizable_ext, min: boolean = !SECRETS.meta.debug): string{
		this.files.push(new ResFile(dir, internal, name, ext, min))
		return ""
	}

	flush(salt: string): string{
		return this.files.map(file => file.html(salt)).join()
	}
}
