<template>
    <div class="form-group">
        <label :id="id" v-if="label">{{ label }}</label>
        <text-input ref="input" :id="id" :class="{'is-invalid': errors }" :placeholder="placeholder" :value="value" @input="$emit('input', $event)" :autofocus="autofocus" :disabled="disabled" />
        <div class="invalid-feedback" v-for="error in errors">{{ error }}</div>
    </div>
</template>

<script>
    import TextInput from '../inputs/TextInput';

    export default {

        props: ['label', 'id', 'placeholder', 'value', 'autofocus', 'errors', 'disabled'],

        components: {
            TextInput,
        },

        created() {
            this.$on('focus', function (msg) {
                this.$refs.input.$emit('focus');
            })
        }

    }
</script>
