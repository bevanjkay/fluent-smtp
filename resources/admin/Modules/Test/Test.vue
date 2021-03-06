<template>
    <div>
        <div class="header">
            Send Test Email
        </div>

        <div class="content">
            <el-form ref="form" :model="form" label-position="left" label-width="120px">

                <el-form-item for="email" label="From">
                    <el-select placeholder="Select Email or Type" :allow-create="true" :filterable="true" v-model="form.from">
                        <el-option
                            v-for="(emailHash, email) in sender_emails"
                            :key="email" :label="email"
                            :value="email"
                        ></el-option>
                    </el-select>

                    <span class="small-help-text" style="display:block;margin-top:-10px">
                        Enter the sender email address (optional).
                    </span>
                </el-form-item>
                
                <el-form-item for="from" label="Send To">
                    <el-input id="from" v-model="form.email" />

                    <span class="small-help-text" style="display:block;margin-top:-10px">
                        Enter email address where test email will be sent (By default, logged in user email will be used if email address is not provide).
                    </span>
                </el-form-item>

                <el-form-item for="isHtml" label="HTML">
                    <el-switch
                        v-model="form.isHtml"
                        active-color="#13ce66"
                        inactive-color="#dcdfe6"
                        active-text="On"
                        inactive-text="Off"
                    />

                    <span class="small-help-text" style="display:block;margin-top:-10px">
                        Send this email in HTML or in plain text format.
                    </span>
                </el-form-item>

                <el-form-item align="left">
                    <el-button
                        type="primary"
                        size="small"
                        icon="el-icon-s-promotion"
                        :loading="loading"
                        @click="sendEmail"
                        :disabled="!maybeEnabled"
                    >Send Test Email</el-button>

                    <el-alert
                        v-if="!maybeEnabled"
                        :closable="false"
                        type="warning"
                        style="display:inline;margin-left:20px;"
                    >{{ inactiveMessage }}</el-alert>
                </el-form-item>
            </el-form>

            <el-alert v-if="debug_info" type="error" :title="debug_info.message" show-icon>
                <div v-for="(error, key) in debug_info.errors" :key="key">
                    {{ error }}
                </div>
            </el-alert>
        </div>
    </div>
</template>

<script>
    import isEmpty from 'lodash/isEmpty'
    export default {
        name: 'EmailTest',
        data() {
            return {
                loading: false,
                debug_info: '',
                form: {
                    from: '',
                    email: '',
                    isHtml: true
                }
            };
        },
        methods: {
            sendEmail() {
                this.loading = true;
                this.debug_info = '';

                this.$post('settings/test', { ...this.form }).then(res => {
                    this.$notify.success({
                        title: 'Great!',
                        offset: 19,
                        message: res.data.message
                    });
                }).fail(res => {
                    if (Number(res.status) === 504) {
                        return this.$notify.error({
                            title: 'Oops!',
                            offset: 19,
                            message: '504 Gateway Time-out.'
                        });
                    }

                    const responseJSON = res.responseJSON;

                    if (responseJSON.data.email_error) {
                        return this.$notify.error({
                            title: 'Oops!',
                            offset: 19,
                            message: responseJSON.data.email_error
                        });
                    }
                    this.debug_info = responseJSON.data;
                }).always(() => {
                    this.loading = false;
                });
            }
        },
        computed: {
            active: function() {
                if (this.settings.misc.is_inactive === 'yes') {
                    return false;
                }
                return true;
            },
            inactiveMessage() {
                const msg = 'Plugin is not configured properly.';

                return msg;
            },
            maybeEnabled() {
                return !isEmpty(this.settings.connections);
            },
            sender_emails() {
                return this.settings.mappings;
            }
        },
        created() {
            this.form.email = this.settings.user_email;
        }
    };
</script>
