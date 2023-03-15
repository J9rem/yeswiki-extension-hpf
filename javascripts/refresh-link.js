/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

(() => {
    class MyError extends Error {
        constructor(message) {
          super(message);
        }
    }
    const paymentHelper = {
        async callApi(url){
            return await fetch(url)
                .then((response)=>{
                    if (!response.ok){
                        return this.getErrorMessage(response)
                            .then(
                                (errorMsg)=>{
                                    throw new MyError(errorMsg)
                                },
                                (error) => {
                                    throw new Error(`bad response (${response.status} ${response.statusText})`)
                                }
                            )
                    }
                    return response.json()
                })
        },
        data: {
            isIframe: null,
            running: false
        },
        async getErrorMessage(response){
            return await response.json().then((json)=>{
                if (typeof json == 'object' && 'error' in json){
                    return json.error
                }
                throw new Error('no error message')
            })
        },
        isIframe(){
            if (this.data.isIframe === null){
                let startStr = wiki.url(`${wiki.pageTag}/iframe`)
                this.data.isIframe = (window.location.href.slice(0,startStr.length) == startStr)
            }
            return this.data.isIframe
        },
        manageError(error,button){
            const msg = (error instanceof MyError) ? error.message+'<br/>' : ''
            this.temporaryTooltip(button,`<b>Une erreur est survenue</b>:<br/>${msg}Veuillez vous rapprocher d'un administrateur du site pour réaliser l'opération manuellement`)
            if (wiki.isDebugEnabled){
                console.log(error)
            }
        },
        onClick(event){
            event.preventDefault()
            if (!this.data.running){
                this.data.running= true
                const target = event.target
                const url = target.getAttribute('href')
                this.toggleButtonStatus(target,true)
                this.callApi(url)
                    .then(this.processResults)
                    .then((data)=>this.updateMessage({data,target}))
                    .finally(()=>{
                        this.toggleButtonStatus(target,false)
                        this.data.running = false
                    })
                    .catch((error)=>this.manageError(error,target))
            } else {
                this.temporaryTooltip(target,_t('HPF_ALREADY_REFRESHING'),'alert alert-warning',2000)
            }
        },
        async processResults(data){
            if (typeof data != 'object'){
                throw new Error('response badly formatted')
            }
            return {needRefresh:('needRefresh' in data && data.needRefresh === true)}
        },
        temporaryTooltip(button,message,className = 'alert alert-danger',duration_ms = 5000){
            if (this.isIframe()){
                const tooltip = $(`<div class="${className}">${message}</div>`)
                $(button).parent().append(tooltip)
                setTimeout(()=>{
                    tooltip.remove()
                },duration_ms)
            } else {
                toastMessage(message,duration_ms,className)
            }
        },
        toggleButtonStatus(button,status){
            if (status){
                button.style.cursor = 'wait'
                $(button).tooltip({
                    placement: 'bottom',
                    title: _t('HPF_REFRESHING'),
                    trigger: 'manual'
                })
                $(button).tooltip('show')
            } else {
                button.style.cursor = null
                if ($(button).data('bs.tooltip')){
                    $(button).tooltip('hide')
                }
            }
        },
        async updateMessage({data,target}){
            if (data.needRefresh){
                this.temporaryTooltip(target,_t('HPF_ALREADY_REFRESHED'),'alert alert-success',2000)
            } else {
                this.temporaryTooltip(target,_t('HPF_ALREADY_NOT_REFRESHED'),'alert alert-primary',2000)
            }
        }
    }
    const links = document.getElementsByClassName('hpf-here-link')
    for (let index = 0; index < links.length; index++) {
        const e = links[index];
        e.addEventListener('click',(e)=>paymentHelper.onClick(e))
        
    }
})()