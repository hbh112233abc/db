# 测试数据

设计一个图书系统的数据库涉及到多个表的设计，以及它们之间的关系。以下是各个表的基本设计和它们之间的关系：

1. **图书表（Books）**
   - BookID: 主键，唯一标识每本书。
   - Title: 书名。
   - ISBN: 国际标准书号。
   - Publisher: 出版社。
   - PublicationDate: 出版日期。
   - Language: 语言。
   - PageCount: 页数。
   - Summary: 摘要或简介。

2. **作者表（Authors）**
   - AuthorID: 主键，唯一标识每位作者。
   - FirstName: 名。
   - LastName: 姓。
   - Biography: 作者简介。

3. **图书分类表（Categories）**
   - CategoryID: 主键，唯一标识每个分类。
   - CategoryName: 分类名称。
   - Description: 分类描述。

4. **读者表（Readers）**
   - ReaderID: 主键，唯一标识每位读者。
   - FirstName: 名。
   - LastName: 姓。
   - Email: 邮箱。
   - RegistrationDate: 注册日期。

5. **借阅记录表（BorrowRecords）**
   - RecordID: 主键，唯一标识每次借阅记录。
   - ReaderID: 外键，关联读者表。
   - BookID: 外键，关联图书表。
   - BorrowDate: 借阅日期。
   - ReturnDate: 归还日期。
   - Status: 借阅状态（借出、已还等）。

6. **图书作者关联表（BookAuthors）**
   - BookID: 外键，关联图书表。
   - AuthorID: 外键，关联作者表。
   - PrimaryAuthor: 标识是否为主作者（可选字段）。

7. **图书分类关联表（BookCategories）**
   - BookID: 外键，关联图书表。
   - CategoryID: 外键，关联图书分类表。

这些表之间的关系如下：
- **图书表**与**作者表**通过**图书作者关联表**多对多关联。
- **图书表**与**图书分类表**通过**图书分类关联表**多对多关联。
- **借阅记录表**与**读者表**和**图书表**分别通过外键关联。


# SQL脚本

- [MySQL]('./sql/mysql.sql')
- [达梦DM8]('./sql/dm.sql')
- [南大通用GBase8s]('./sql/gbase.sql')


# 南大通用GBase8s

- windows系统只有odbc,pdo_odbc扩展, 当前使用pdo_odbc,windows环境编译pdo_gbasedbt报错(跟踪)
- linux系统有odbc,pdo_odbc,pdo_gbasedbt,推荐使用pdo_gbasedbt
- 表中包含text字段,无法insert或update直接插入或修改对应字段数据,使用pdo支持参数绑定操作,或者先把值放入系统文件,然后如下sql操作
    ```sql
    insert into tab(id, col) values (1, filetoclob('/home/gbase/text.file','client'));
    ```
- 字段blob或clob,pdo_odbc操作会报错(跟踪),pdo_gbasedbt不会报错
    ```shell
    Restricted data type attribute violation: -11013 [GBasedbt][GBasedbt ODBC Driver]Restricted data type attribute violation. (SQLExecute[-11013] at ext\pdo_odbc\odbc_stmt.c:257)
    ```
- pdo_odbc扩展 parpare($sql),如果$sql中包含中文会报错(跟踪),pdo_gbasedbt不会报错
