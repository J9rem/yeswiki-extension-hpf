/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// import SpinnerLoader from './SpinnerLoader.js'

let rootsElements = ['.payment-input-field'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: {vuejsDatepicker/*,SpinnerLoader*/},
    data: function() {
        return {
          // currentFormIdInternal: null,
          datePickerLang: vdp_translation_index,
          datePickerLanguage: null,
          defaultPayment: {
            id: '',
            origin: '',
            customDate: '',
            refreshing: false,
            visibility: true,
            total: 0
          },
          payments: [],
        };
    },
    computed: {
      // currentFormId(){
      //   if (this.currentFormIdInternal === null){
      //     const formInput = $(this.element).closest('form#formulaire').find('input[name=id_typeannonce][type=hidden]')
      //     if (formInput && formInput.length > 0){
      //       this.currentFormIdInternal = $(formInput).val()
      //     }
      //   }
      //   return this.currentFormIdInternal
      // },
      element(){
        return $(isVueJS3 ? this.$el.parentNode : this.$el)
      },
      value(){
        return this.payments.length === 0 
          ? '' 
          : JSON.stringify(
              Object.fromEntries(
                this.payments.filter((payment)=>{
                  return payment.id.length > 0 &&
                    payment.origin.length > 0 &&
                    this.generateDate(payment).length > 0
                }).map((payment)=>{
                  let data = {...payment}
                  delete data.id
                  delete data.refreshing
                  delete data.visibility
                  delete data.customDate;
                  ['adhesion','adhesion_groupe','don'].forEach((name)=>{
                    if (!(name in data) || data[name].length === 0){
                      delete data[name]
                    } else {
                      data[name] = Object.fromEntries(data[name])
                    }
                  })
                  data.date = this.generateDate(payment)
                  return  [
                    payment.id,
                    data
                  ]
                })
              )
            )
      }
    },
    methods:{
      assignValueReactive(obj,original){
        for (const key in original) {
          this.$set(obj,key,original[key])
        }
      },
      createPayment(){
        let newVal = {}
        this.assignValueReactive(newVal,this.defaultPayment)
        this.payments.push(newVal)
      },
      createSubElem(key,elemKey){
        if (!(elemKey in this.payments[key])){
          this.$set(this.payments[key],elemKey,[])
        }
        this.payments[key][elemKey].push(['',''])
      },
      customFormatterDate(date){
        const dd = (new Date(date))
        let day = dd.getDate()
        if (day < 10){
          day = `0${day}`
        }
        let month = dd.getMonth()+1
        if (month < 10){
          month = `0${month}`
        }
        const year = dd.getFullYear()
        return `${day}/${month}/${year}`

      },
      convertPaymentsToArray(payments){
        return Object.entries(payments).map(this.generatePaymentFromArray)
      },
      generateDate(payment){
        if (String(payment.customDate).length == 0){
          return '';
        }
        const parsed = Date.parse(payment.customDate)
        if (isNaN(parsed)){
          console.log({notADate:payment.customDate,payment:{...payment}})
          return ''
        }
        const date = new Date(parsed)
        date.setHours(12) // to prevent errors with change time UTC/locale
        return date.toISOString().slice(0,10)
      },
      generatePaymentFromArray([id,value]){
        let data = {}
        this.assignValueReactive(data,this.defaultPayment)
        this.assignValueReactive(data,value)
        this.$set(data,'visibility',true);
        ['adhesion','adhesion_groupe','don'].forEach((name)=>{
          if (name in data){
            if (Object.keys(data[name]).length > 0){
              data[name] = Object.entries(data[name])
            } else {
              this.$delete(data,name)
            }
          }
        })
        data.id = id
        if ('date' in value && value.date.length > 0){
          const parsed = Date.parse(value.date)
          if (!isNaN(parsed)){
            data.customDate = (new Date(parsed)).toISOString().slice(0,10)
          }
        }
        return data
      },
      removeSubElem(key,elemKey,keyYear){
        this.payments[key][elemKey].splice(keyYear,1)
      },
      refreshPayment(key){
        this.payments[key].refreshing = true
        const id = String(this.payments?.[key]?.id)
        if (id.length === 0){
          return
        }
        fetch(`?api/hpf/helloasso/payment/info/${id}`)
          .then((response)=>{
            if (response.ok){
              return response.json()
            }
            throw new Error(`response not ok (${response.status} - ${response.statusText}) for id:${id}`)
          })
          .then((data)=>{
            if (data?.found && data?.id === id){
              const date = data?.date ?? ''
              const value = data?.amount
              const form = data?.form
              const currentKeys = Object.keys(this.payments).filter((k)=>this.payments[k]?.id == id)
              if (currentKeys.length > 0){
                const currentKey = currentKeys[0]
                if (date.length > 0){
                  this.payments[currentKey].customDate = this.generateDate({customDate:date})
                }
                if (value !== null){
                  this.payments[currentKey].total = value
                }
                if (form && form > 0){
                  this.payments[currentKey].origin = `helloasso:${form}`
                }
              }
            }
          })
          .catch((error)=>{
            console.error(error)
          })
          .finally(()=>{
            this.payments[key].refreshing = false
          })
      },
      removePayment(keyToRemove){
        this.payments.splice(keyToRemove,1)
      },
      sortArrayDateThenIdDesc(array){
        return array.sort((a,b)=>{
          const result = (a.date == b.date) ? 0 : b.date > a.date
          return (result === 0) ? (b.id >= a.id): result
        })
      },
      updateDate({key,date}){
        if (date !== null){
          this.payments[key].customDate = date
        }
      }
    },
    mounted(){
      const el = this.element
      this.datePickerLanguage = (wiki.locale in this.datePickerLang)
        ? this.datePickerLang[wiki.locale]
        : this.datePickerLang.en
      let importedPayments = {}
      try {
        importedPayments = JSON.parse(el[0].dataset.payments)
      } catch (error) {
      }
      // empty this.payments
      this.payments.splice(0,this.payments.length)
      // add element with reactivity
      this.sortArrayDateThenIdDesc(this.convertPaymentsToArray(importedPayments)).forEach((el)=>{
        this.payments.push(el)
      })
    }
};

if (isVueJS3){
  let app = Vue.createApp(appParams);
  app.config.globalProperties.wiki = wiki;
  app.config.globalProperties._t = _t;
  rootsElements.forEach(elem => {
      app.mount(elem);
  });
} else {
  Vue.prototype.wiki = wiki;
  Vue.prototype._t = _t;
  rootsElements.forEach(elem => {
      new Vue({
          ...{el:elem},
          ...appParams
      });
  });
}