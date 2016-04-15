mod {
    web_layout {
        BackendLayouts {
            #The template identifier 'homepage'
            myhomepage {
                # The title visible in the BE
                title = My Homepage
                # additional image for the BE
                icon = EXT:site_template/Resources/Public/Backend/BackendLayoutIcons/homepage.jpg
                config {
                    backend_layout {
                        # max number of columns
                        colCount = 3
                        # max number of rows
                        rowCount = 3
                        rows {
                            1 {
                                columns {
                                    1 {
                                        name = Top row 100% width
                                        # An unique identifier for this specific column
                                        colPos = 3
                                        # A additional colspan like normal table colspans
                                        colspan = 3
                                    }
                                }
                            }

                            2 {
                                columns {
                                    1 {
                                        name = Second row 66% width
                                        colspan = 2
                                        colPos = 0
                                    }

                                    2 {
                                        name = Second row right 33% width
                                        colPos = 2
                                    }
                                }
                            }
                            3 {
                                columns {
                                    1 {
                                        name = Left 33% width
                                        colPos = 4
                                    }
                                    2 {
                                        name = Center 33% width
                                        colPos = 5
                                    }
                                    3 {
                                        name = Center 33% width
                                        colPos = 6
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}