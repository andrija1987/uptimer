var app = new Vue({
    el: '#servers',
    data: {
        showAddModal: false,
        showEditModal: false,
        showDeleteModal: false,
        errorMessage: "",
        successMessage: "",
        servers: [],
        newServer: {
            id: '',
            address: '',
            type: '',
            findString: '',
            timeout: ''
        },
        clickServer: {}
    },

    mounted: function() {
        this.getAllServers();
    },

    methods: {
        getAllServers: function() {
            axios.get('api.php')
                .then(function(response) {
                    //console.log(response);
                    if (response.data.error) {
                        app.errorMessage = response.data.message;
                    } else {
                        app.servers = response.data.servers;
                    }
                });
        },

        saveServer: function() {
            //console.log(app.newServer);
            var memForm = app.toFormData(app.newServer);
            axios.post('api.php?crud=create', memForm)
                .then(function(response) {
                    //console.log(response);
                    app.newServer = {
                        address: '',
                        type: '',
                        findString: '',
                        timeout: ''
                    };
                    if (response.data.error) {
                        app.errorMessage = response.data.message;
                    } else {
                        app.successMessage = response.data.message
                        app.getAllServers();
                    }
                });
        },

        updateServer() {
            var memForm = app.toFormData(app.clickServer);
            axios.post('api.php?crud=update', memForm)
                .then(function(response) {
                    //console.log(response);
                    app.clickServer = {};
                    if (response.data.error) {
                        app.errorMessage = response.data.message;
                    } else {
                        app.successMessage = response.data.message
                        app.getAllServers();
                    }
                });
        },

        deleteServer() {
            var memForm = app.toFormData(app.clickServer);
            axios.post('api.php?crud=delete', memForm)
                .then(function(response) {
                    //console.log(response);
                    app.clickServer = {};
                    if (response.data.error) {
                        app.errorMessage = response.data.message;
                    } else {
                        app.successMessage = response.data.message
                        app.getAllServers()();
                    }
                });
        },

        selectServer(server) {
                app.clickServer = server;
        },

        toFormData: function(obj) {
            var form_data = new FormData();
            for (var key in obj) {
                    form_data.append(key, obj[key]);
            }
            return form_data;
        },

        clearMessage: function() {
            app.errorMessage = '';
            app.successMessage = '';
        }

    }
});