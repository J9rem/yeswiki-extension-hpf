/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
import InputList from '../../../../aceditor/presentation/javascripts/components/InputList.js'
import InputHelper from '../../../../aceditor/presentation/javascripts/components/InputHelper.js'

let cache = null

const getForms = async () => {
  return await fetch(wiki.url('?api/forms'))
  .then((response)=>{
    if (response.ok){
      return response.json()
    }
    throw new Error(`response not ok ; cde : ${response.status} (${response.statusText})`)
  })
  .then((data)=>{
    if (typeof data !== 'object' && !Array.isArray(data)){
      throw new Error('data badly formatted')
    }
    const forms = {}
    if (Array.isArray(data)){
      data.forEach((form,id)=>{
        forms[id] = form
      })
    } else {
      Object.keys(data).forEach((id)=>{
        forms[id] = data[id]
      })
    }
    return forms
  })
  .catch((error)=>{
    console.error(error)
    return {}
  })
}

const constructInput = (forms) => {
  return {
    props: InputList.props.filter((value)=>value!=='selectedForms'),
    mixins: [InputHelper],
    data(){
      return {
        selectedForms: forms
      }
    },
    computed: {
      optionsList() {
          return Object.fromEntries(Object.entries(this.selectedForms)
            .map(([id,form])=>[id,`${form.bn_label_nature ?? 'form'} (${id})`]))
        }
    },
    template: InputList.template
  }
}

export default function (resolve,reject) {
  if (cache === null){
    getForms().catch((error)=>{
      console.error(error)
      return {}
    })
      .then((forms)=>{
        cache = constructInput(forms)
        resolve(cache)
      })
      .catch((error)=>{
        reject(error)
      })
  } else {
    resolve(cache)
  }
}
