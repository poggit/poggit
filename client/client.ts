console.info("Help us improve Poggit on GitHub: https://github.com/poggit/poggit")

correctPath()

const $document = $(document)
$document.find(".navbutton").each(initNavButton)
$document.tooltip({
	content: function(this: HTMLElement){
		const $this = $(this)
		return $this.hasClass("html-tooltip") ? $this.prop("title") : $("<span></span>").text($this.prop("title")).html()
	},
})
$document.find("#gh-login").click(() => login())
$document.find("#toggle-wrapper").each(initToggle)
$document.find(".dynamic-anchor").each(initDynamicAnchor)
$document.find("#hide-tos-button").click(() => csrf("session/hideTos", {}, () =>{
	const remindTos = document.getElementById("remind-tos") as HTMLDivElement
	remindTos.style.display = "none"
}))
$document.find("#nav-logout").click(() => csrf("login/logout", {}, () => window.location.reload()))

setInterval(refreshTime, 300)
setInterval(keepOnline, 60000)
keepOnline()

function correctPath(){
	const pathMap: StringMap<string> = {
		pi: "plugins",
		index: "plugins",
		release: "p",
		rel: "p",
		plugin: "p",
		build: "ci",
		b: "ci",
		dev: "ci",
	}

	if(!PoggitConsts.Debug &&
		(window.location.protocol.replace(":", "") !== "https" ||
			location.host !== "poggit.pmmp.io")){
		location.replace("https://poggit.pmmp.io" + window.location.pathname)
	}

	const path = location.pathname.split("/", 3)
	if(path.length === 3 && pathMap[path[1]] !== undefined){
		history.replaceState(null, "", `/${pathMap[path[1]]}/${path[2]}`)
	}
}

function initNavButton(this: HTMLElement): void{
	if(this.hasAttribute("data-navbutton-init")){
		return
	}
	this.setAttribute("data-navbutton-init", "true")

	const $this = $(this)
	let target = this.getAttribute("data-target")
	let internal: boolean
	if(internal = !$this.hasClass("extlink")){
		target = "/" + target
	}
	const wrapper = $("<a></a>")
	wrapper.addClass("navlink")
	wrapper.attr("href", target)
	if(!internal){
		wrapper.attr("target", "_blank")
	}
	$this.wrapInner(wrapper)
}

function initToggle(this: HTMLElement): void{
	if(this.hasAttribute("data-toggle-init")){
		return
	}
	this.setAttribute("data-toggle-init", "true")

	const $holder = $(this)
	let name: string | null = this.getAttribute("data-name")
	let escape = false
	if(name === null){
		name = this.getAttribute("data-escaped-name")
		escape = true
		if(name === null){
			throw new Error("Toggle name missing")
		}
	}
	const opened = this.getAttribute("data-opened") === "true"

	const wrapper = $("<div class='wrapper' data-opened='false'></div>")
	$holder.wrapInner(wrapper)
	const header = $("<h5 class='wrapper-header'></h5>")
	if(escape){
		header.text(name)
	}else{
		header.html(name)
	}

	const img = $("<img width='24' class='wrapper-toggle-button'/>")
		.attr("src", "/res/expand_arrow-24.png")

	const collapseAction = () =>{
		wrapper.attr("data-opened", "false")
		$holder.css("display", "none")
		img.attr("src", "/res/expand_arrow-24.png")
	}
	const expandAction = () =>{
		wrapper.attr("data-opened", "true")
		$holder.css("display", "flex")
		img.attr("src", "/res/collapse_arrow-24.png")
	}

	wrapper.prepend(header.append(img.click(() =>{
		if(wrapper.attr("data-opened") === "true"){
			collapseAction()
		}else{
			expandAction()
		}
	})))

	if(opened){
		expandAction()
	}else{
		collapseAction()
	}
}

function initDynamicAnchor(this: HTMLElement): void{
	if(this.hasAttribute("data-dynamic-anchor-init")){
		return
	}
	this.setAttribute("data-dynamic-anchor-init", "true")

	const $this = $(this)
	const parent = $this.parent()
	parent.hover(() => $this.css("visibility", "visible"), () => $this.css("visibility", "hidden"))
}

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
const WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]

function refreshTime(){
	const now = new Date()
	const nowTimestamp = now.getTime()

	$(".time, .time-elapse") // they should just be the same
		.each(function(this: HTMLElement){
			const timestamp = Number(this.getAttribute("data-timestamp") as string)
			const date = new Date(timestamp)
			const timeDiff = Math.abs(nowTimestamp - timestamp)

			this.title = `${date.toLocaleTimeString()}, ${date.toLocaleDateString()}`

			const hours: string = date.getHours() < 10 ? `0${date.getHours()}` : date.getHours().toString()
			const minutes: string = date.getMinutes() < 10 ? `0${date.getMinutes()}` : date.getMinutes().toString()
			const seconds: string = date.getSeconds() < 10 ? `0${date.getSeconds()}` : date.getSeconds().toString()

			if(now.getFullYear() !== date.getFullYear()){
				this.innerText = `${MONTHS[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`
				return
			}
			if(timeDiff > 86400e+3 * 7){
				this.innerText = `${MONTHS[date.getMonth()]} ${date.getDate()}, ${hours}:${minutes}:${seconds}`
				return
			}
			if(now.getDate() !== date.getDate()){ // within one week, different day
				this.innerText = `${WEEKDAYS[date.getDay()]}, ${hours}:${minutes}:${seconds}`
				return
			}
			if(timeDiff > 3600e+3 * 4){
				this.innerText = `${hours}:${minutes}:${seconds}`
				return
			}
			let text = ""
			if(timeDiff >= 3600e+3){
				text = `${Math.floor(timeDiff / 3600e+3)} hours `
			}else if(timeDiff >= 60e+3){
				text = `${Math.floor(timeDiff / 60e+3)} minutes `
			}else{
				text = `${Math.floor(timeDiff / 1e+3)} seconds `
			}
			this.innerText = text + (nowTimestamp > timestamp ? "ago" : "later")
		})
}

function keepOnline(){
	csrf("session/online", {}, (onlineCount: number) => $("#online-user-count").text(`${onlineCount} online`).css("display", "list-item"))
}

function csrf<Req extends {} = {}, Res  = {}>(
	path: string,
	data: Req = {} as Req,
	success: (res: Res) => void = nop,
	error: (message: string) => void = (message: string) => alert(`Error POSTing ${path}: ${message}`),
){
	$.post(`/csrf`, {}, (token: string) =>{
		$.ajax("/csrf/" + path, {
			dataType: "json",
			data: JSON.stringify(data),
			headers: {
				"Content-Type": "application/json",
				"X-Poggit-CSRF": token,
			},
			method: "POST",
			success: (data: {success: true, data: Res} | {success: false, message: string}) =>{
				if(data.success){
					success(data.data)
				}else{
					error(data.message)
				}
			},
		})
	})
}

function login(nextStep: string = window.location.toString()){
	csrf("login/persistLoc", {path: nextStep}, (data: {state: string}) =>{
		window.location.assign(`https://github.com/login/oauth/authorize?client_id=${PoggitConsts.App.ClientId}&state=${data.state}`)
	})
}

function nop(){
}
