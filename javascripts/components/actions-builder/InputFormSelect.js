/*
 * This file is part of the YesWiki Extension hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// let mixins = [];
// try {
//   let {default: InputHelper} = await import('../../../../zfuture43/javascripts/components/InputHelper.js');
//   if (InputHelper && typeof InputHelper != "undefined"){
//     mixins.push(InputHelper);
//   }
// } catch (error) {
// }
// if (mixins.length == 0){
//   try {
//     let {default: InputHelper} = await import('../../../../aceditor/presentation/javascripts/components/InputHelper.js');
//     if (InputHelper && typeof InputHelper != "undefined"){
//       mixins.push(InputHelper);
//     }
//   } catch (error) {
//   }
// }

export default {
    props: [ 'value', 'config', 'selectedForm' ],
    // mixins: mixins,
    computed: {
      optionsList: function() {
        let forms = this.$root.formIds;
        if (this.selectedForm && this.selectedForm.hasOwnProperty('bn_id_nature') && this.selectedForm.bn_id_nature.length != 0){
          let results = {};
          Objects.keys(forms).forEach((id)=>{
            if (id != this.selectedForm.bn_id_nature){
              results[id] = forms[id];
            }
            return results;
          });
        } else {
          return forms;
        }
      }
    },
    mounted() {
      if (!this.value && this.config.value) this.$emit('input', this.config.value)
    },
    template: `
      <div class="form-group input-group" :class="config.type" :title="config.hint" >
        <addon-icon :config="config" v-if="config.icon"></addon-icon>
        <label v-if="config.label" class="control-label">{{ config.label }}</label>
        <select :value="value" v-on:input="$emit('input', $event.target.value)"
                :required="config.required" class="form-control">
          <option value=""></option>
          <option v-for="(optLabel, optValue) in optionsList" :value="optValue" :selected="value == optValue">
            {{ optLabel }}
          </option>
        </select>
        <input-hint :config="config"></input-hint>
      </div>
      `
  }
  